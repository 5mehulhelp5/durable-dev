<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable;

use Gplanchat\Durable\SystemClock;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

/**
 * The core's default clock (#617): the one place in the core that reads the current time.
 */
final class SystemClockTest extends TestCase
{
    public function testItReadsTheCurrentTimeInUtc(): void
    {
        $clock = new SystemClock();
        $before = microtime(true);
        $now = $clock->now();
        $after = microtime(true);

        self::assertInstanceOf(ClockInterface::class, $clock);
        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertGreaterThanOrEqual(floor($before * 1_000_000.0), (float) $now->format('Uu'));
        self::assertLessThanOrEqual(ceil($after * 1_000_000.0), (float) $now->format('Uu'));
    }
}
