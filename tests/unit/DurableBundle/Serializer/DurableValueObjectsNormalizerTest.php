<?php

declare(strict_types=1);

namespace unit\Gplanchat\DurableBundle\Serializer;

use Gplanchat\Durable\Activity\RetryLimit;
use Gplanchat\Durable\Bundle\Serializer\DurationNormalizer;
use Gplanchat\Durable\Bundle\Serializer\RetryLimitNormalizer;
use Gplanchat\Durable\Bundle\Serializer\TaskQueueNormalizer;
use Gplanchat\Durable\Duration;
use Gplanchat\Durable\TaskQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * #643: the edges a message rarely carries, infinity and an unlimited retry, cross JSON too, and
 * a task queue, which the ObjectNormalizer rebuilds without its name.
 */
final class DurableValueObjectsNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{object}>
     */
    public static function values(): iterable
    {
        yield 'zero' => [Duration::zero()];
        yield 'milliseconds' => [Duration::milliseconds(250.0)];
        yield 'infinity' => [Duration::infinity()];
        yield 'unlimited' => [RetryLimit::unlimited()];
        yield 'once' => [RetryLimit::once()];
        yield 'five attempts' => [RetryLimit::ofAttempts(5)];
        yield 'task queue' => [TaskQueue::named('quotes')];
    }

    #[DataProvider('values')]
    public function testAValueReadsBackEqual(object $value): void
    {
        $serializer = new Serializer([new DurationNormalizer(), new RetryLimitNormalizer(), new TaskQueueNormalizer()], [new JsonEncoder()]);

        self::assertEquals($value, $serializer->deserialize($serializer->serialize($value, 'json'), $value::class, 'json'));
    }
}
