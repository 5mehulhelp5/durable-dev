---
title: Encrypting payloads
weight: 31
---

# Encrypting payloads

With a Temporal server run by someone else, Temporal Cloud for instance, every payload Durable
sends is stored in the provider's history: workflow inputs and results, activity arguments and
results, signals, updates, queries, memos and headers. TLS protects the transport, not the
storage. An order number or an e-mail address in a payload is personal data handed to a processor.

A **payload codec** closes most of that gap. It encodes every payload before it leaves the
application and decodes every payload that comes back, so the server stores ciphertext. Durable
applies it at the workflow service client, where every call to Temporal passes
([DUR055](https://github.com/gplanchat/durable-dev/blob/main/documentation/adr/DUR055-a-payload-codec-at-the-client-boundary.md)).

The codec belongs to the **Temporal backend** only. The in-memory and SQL journals stay in your own
database, and Messenger messages never pass through the codec.

---

## Durable ships the interface, not a cipher

`Gplanchat\Bridge\Temporal\Codec\PayloadCodecInterface` has two methods, `encode(Payload): Payload`
and `decode(Payload): Payload`. The contract is Temporal's:

- an encoded payload marks itself in its metadata;
- `decode()` returns a payload without that mark unchanged, so history written before the codec was
  enabled stays readable;
- `decode()` throws on a payload it recognises but cannot decode, an unknown key for instance.

Durable provides no implementation. The algorithm, the keys and their rotation are yours. The class
below is an **example** to start from, not a class Durable ships or supports.

## An example codec: libsodium XChaCha20-Poly1305

```php
<?php

declare(strict_types=1);

namespace App\Temporal;

use Gplanchat\Bridge\Temporal\Codec\PayloadCodecInterface;
use Temporal\Api\Common\V1\Payload;

final class SodiumPayloadCodec implements PayloadCodecInterface
{
    private const ENCODING = 'binary/encrypted';
    private const NONCE_BYTES = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

    /**
     * @param array<string, string> $keys        key id => 32-byte raw key, every key still needed to read history
     * @param string                $activeKeyId the key new payloads are sealed with
     */
    public function __construct(
        private readonly array $keys,
        private readonly string $activeKeyId,
    ) {
        if (SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES !== \strlen($keys[$activeKeyId] ?? '')) {
            throw new \InvalidArgumentException('The active key must be 32 raw bytes.');
        }
    }

    public function encode(Payload $payload): Payload
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        // The whole payload is sealed, its own metadata included, so decode() restores it exactly.
        $sealed = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $payload->serializeToString(),
            self::additionalData($this->activeKeyId),
            $nonce,
            $this->keys[$this->activeKeyId],
        );

        return new Payload([
            'metadata' => ['encoding' => self::ENCODING, 'encryption-key-id' => $this->activeKeyId],
            'data' => $nonce . $sealed,
        ]);
    }

    public function decode(Payload $payload): Payload
    {
        $metadata = iterator_to_array($payload->getMetadata());
        if (self::ENCODING !== ($metadata['encoding'] ?? null)) {
            return $payload; // written before the codec was enabled
        }
        $keyId = $metadata['encryption-key-id'] ?? '';
        $key = $this->keys[$keyId] ?? throw new \RuntimeException(\sprintf('No key "%s" in the keyring.', $keyId));
        $data = $payload->getData();
        if (\strlen($data) < self::NONCE_BYTES) {
            throw new \RuntimeException('The encrypted payload is truncated.');
        }
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($data, self::NONCE_BYTES),
            self::additionalData($keyId),
            substr($data, 0, self::NONCE_BYTES),
            $key,
        );
        if (false === $plain) {
            throw new \RuntimeException(\sprintf('The payload does not authenticate with key "%s".', $keyId));
        }
        $decoded = new Payload();
        $decoded->mergeFromString($plain);

        return $decoded;
    }

    private static function additionalData(string $keyId): string
    {
        return self::ENCODING . "\0" . $keyId;
    }
}
```

What it does, byte for byte, so a peer written in another language can read the same history:

- **Plaintext**: the whole `Payload` message, serialized as protobuf. Its own metadata (`encoding`:
  `json/plain`, for instance) is sealed with its data, and `decode()` returns it exactly.
- **Outer metadata**: `encoding` = `binary/encrypted`, `encryption-key-id` = the key id.
- **Data**: a random 24-byte nonce, then the ciphertext and its 16-byte tag.
- **Associated data**: `binary/encrypted`, a NUL byte, then the key id. A payload whose key id or
  encoding is rewritten no longer authenticates.

A random nonce makes two encodings of the same value differ. Replay is not affected: Durable compares
plain values, before encoding and after decoding.

### Keys and rotation

Generate a key and keep it as base64 in your secrets:

```bash
php -r 'echo base64_encode(sodium_crypto_aead_xchacha20poly1305_ietf_keygen()), PHP_EOL;'
```

The keyring maps key ids to keys. To rotate, add a new key, make it the active one and deploy:
new payloads are sealed with it, older history still decodes with the old one. **Never drop a key
while a run sealed with it is still within the namespace's retention**: that run can no longer be
read, by a worker or by a dashboard. Removing the codec altogether has the same effect.

Every process that talks to the namespace needs the same codec in the same deployment: the
workers, whatever starts or signals workflows, and the dashboard. A worker that cannot decode a
task's history fails that task, and Temporal retries it; a dashboard shows a read failure. Neither
shows ciphertext as if it were data.
