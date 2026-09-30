---
title: Chiffrer les payloads
weight: 31
---

# Chiffrer les payloads

Avec un serveur Temporal opéré par un tiers, Temporal Cloud par exemple, chaque payload envoyé par
Durable est stocké dans l'historique du fournisseur : entrées et résultats des workflows, arguments
et résultats des activités, signaux, mises à jour, requêtes, mémos et en-têtes. TLS protège le
transport, pas le stockage. Un numéro de commande ou une adresse e-mail dans un payload, ce sont
des données personnelles confiées à un sous-traitant.

Un **codec de payload** comble l'essentiel de cet écart. Il encode chaque payload avant qu'il ne
quitte l'application et décode chaque payload qui revient : le serveur ne stocke que du chiffré.
Durable l'applique au client du service de workflows, par où passe chaque appel à Temporal
([DUR055](https://github.com/gplanchat/durable-dev/blob/main/documentation/adr/DUR055-a-payload-codec-at-the-client-boundary.md)).

Le codec ne concerne que le **backend Temporal**. Les journaux en mémoire et SQL restent dans votre
propre base, et les messages Messenger ne passent jamais par le codec.

---

## Durable fournit l'interface, pas le chiffrement

`Gplanchat\Bridge\Temporal\Codec\PayloadCodecInterface` a deux méthodes, `encode(Payload): Payload`
et `decode(Payload): Payload`. Le contrat est celui de Temporal :

- un payload encodé se signale dans ses métadonnées ;
- `decode()` rend tel quel un payload qui ne porte pas cette marque, si bien que l'historique écrit
  avant l'activation du codec reste lisible ;
- `decode()` lève une exception sur un payload qu'il reconnaît mais ne sait pas décoder, par exemple
  à cause d'une clé inconnue.

Durable ne fournit aucune implémentation. L'algorithme, les clés et leur rotation vous
appartiennent. La classe ci-dessous est un **exemple** dont partir, pas une classe que Durable
livre ou maintient.

## Un exemple de codec : XChaCha20-Poly1305 avec libsodium

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

Voici ce qu'il fait, octet par octet, pour qu'un pair écrit dans un autre langage puisse lire le
même historique :

- **Texte clair** : le message `Payload` entier, sérialisé en protobuf. Ses propres métadonnées
  (`encoding` : `json/plain`, par exemple) sont scellées avec ses données, et `decode()` les rend à
  l'identique.
- **Métadonnées extérieures** : `encoding` = `binary/encrypted`, `encryption-key-id` = l'id de la
  clé.
- **Données** : un nonce aléatoire de 24 octets, puis le chiffré et son tag de 16 octets.
- **Données associées** : `binary/encrypted`, un octet NUL, puis l'id de la clé. Un payload dont on
  réécrit l'id de clé ou l'encodage ne s'authentifie plus.

Avec un nonce aléatoire, deux encodages d'une même valeur diffèrent. Le rejeu n'en souffre pas :
Durable compare des valeurs en clair, avant l'encodage et après le décodage.

### Clés et rotation

Générez une clé et conservez-la en base64 dans vos secrets :

```bash
php -r 'echo base64_encode(sodium_crypto_aead_xchacha20poly1305_ietf_keygen()), PHP_EOL;'
```

Le trousseau associe des ids de clé à des clés. Pour faire tourner les clés, ajoutez-en une
nouvelle, faites-en la clé active et déployez : les nouveaux payloads sont scellés avec elle,
l'historique plus ancien se décode toujours avec l'ancienne. **Ne retirez jamais une clé tant
qu'une exécution scellée avec elle reste dans la durée de rétention du namespace** : cette
exécution ne serait plus lisible, ni par un worker ni par un tableau de bord. Retirer le codec
lui-même a le même effet.

Chaque processus qui parle au namespace a besoin du même codec, dans le même déploiement : les
workers, ce qui démarre ou signale des workflows, et le tableau de bord. Un worker qui ne sait pas
décoder l'historique d'une tâche fait échouer cette tâche, et Temporal la relance ; un tableau de
bord affiche une erreur de lecture. Ni l'un ni l'autre ne présente du chiffré comme s'il s'agissait
de données.
