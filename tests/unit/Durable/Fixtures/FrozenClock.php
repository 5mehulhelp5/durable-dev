<?php

declare(strict_types=1);

namespace unit\Durable\Fixtures;

use Psr\Clock\ClockInterface;

/**
 * A clock that only moves when the test says so (#617).
 */
final class FrozenClock implements ClockInterface
{
    private \DateTimeImmutable $now;

    public function __construct(float $seconds = 1_700_000_000.0)
    {
        $this->set($seconds);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function set(float $seconds): void
    {
        $now = \DateTimeImmutable::createFromFormat('U.u', \sprintf('%.6F', $seconds), new \DateTimeZone('UTC'));
        \assert(false !== $now);
        $this->now = $now;
    }

    public function advance(float $seconds): void
    {
        $this->set((float) $this->now->format('U.u') + $seconds);
    }
}
