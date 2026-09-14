<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable\Observation;

use Gplanchat\Durable\Observation\RecordedDetails;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The four ways a naive JSON round trip betrays — each one observed at runtime before being
 * written here.
 *
 * A storage barrier is judged on what it does with hostile inputs, not on what it does with
 * ordinary ones. Three of these cases went straight through.
 */
final class RecordedDetailsStorableTest extends TestCase
{
    /**
     * `json_encode` calls the payload's `jsonSerialize()`: business code, which may throw. No flag
     * covers that case, and the exception would climb up to `collect()`, that is `kernel.response`
     * — the request falls, whereas the original defect only broke the profile write on
     * `kernel.terminate`.
     */
    public function testAPayloadWhoseSerialisationThrowsDoesNotBringTheCallerDown(): void
    {
        $trap = new class implements \JsonSerializable {
            public function jsonSerialize(): mixed
            {
                throw new \RuntimeException('business code, inside the profiler');
            }
        };

        self::assertNull(RecordedDetails::storable(['payload' => $trap]));
    }

    /**
     * Beyond 512 levels, `json_decode` returns `null` where encoding had produced text. The value
     * disappears — that is accepted — but the caller must be able to put the result in a typed
     * property without throwing, hence the key-by-key application on the collector side.
     */
    public function testNestingDeeperThanJsonHoldsYieldsNull(): void
    {
        $deep = 'bottom';
        for ($i = 0; $i < 600; ++$i) {
            $deep = [$deep];
        }

        self::assertNull(RecordedDetails::storable($deep));
    }

    /**
     * The frieze bounds are declared `float`. Without `JSON_PRESERVE_ZERO_FRACTION`, a duration of
     * exactly three seconds comes back as `int` and the declared type lies.
     */
    public function testAnIntegerValuedFloatStaysAFloat(): void
    {
        $storable = RecordedDetails::storable(['spanSec' => 3.0, 'tMin' => 0.0]);

        self::assertIsArray($storable);
        self::assertIsFloat($storable['spanSec']);
        self::assertIsFloat($storable['tMin']);
    }

    #[DataProvider('ordinaryPayloads')]
    public function testWhatWasReadableStaysIdentical(mixed $value): void
    {
        self::assertSame($value, RecordedDetails::storable($value));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function ordinaryPayloads(): iterable
    {
        yield 'string' => ['hello'];
        yield 'integer' => [42];
        yield 'float' => [1.5];
        yield 'boolean' => [true];
        yield 'null' => [null];
        yield 'list' => [[1, 2, 3]];
        yield 'associative array' => [['a' => 1, 'b' => ['c' => 'd']]];
    }

    /**
     * A recursive reference, on the other hand, survives: `JSON_PARTIAL_OUTPUT_ON_ERROR` cuts it
     * and returns the rest. The case is here so we stop believing it broken.
     */
    public function testARecursiveReferenceIsTruncatedNotLost(): void
    {
        $object = new \stdClass();
        $object->name = 'loop';
        $object->self = $object;

        $storable = RecordedDetails::storable(['payload' => $object]);

        self::assertIsArray($storable);
        self::assertSame('loop', $storable['payload']['name']);
    }
}
