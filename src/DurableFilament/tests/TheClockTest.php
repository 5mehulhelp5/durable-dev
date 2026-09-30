<?php

declare(strict_types=1);

namespace Gplanchat\Durable\Filament\Tests;

use Gplanchat\Durable\Observation\RunDashboard;
use Gplanchat\Durable\SystemClock;
use Psr\Clock\ClockInterface;

/**
 * The dashboard measures waiting times against the clock durable-laravel binds, resolved by its
 * interface rather than by a string id a rename would break (#879).
 */
final class TheClockTest extends PanelTestCase
{
    public function testTheDashboardReadsTheClockBoundByItsInterface(): void
    {
        $clock = new class implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('@1700000000');
            }
        };
        $this->app->instance(ClockInterface::class, $clock);
        // Detached from the interface: a dashboard still reading the string id gets this one.
        $this->app->instance('durable.clock', new SystemClock());
        $this->app->forgetInstance(RunDashboard::class);

        $dashboard = $this->app->make(RunDashboard::class);

        self::assertSame($clock, (new \ReflectionProperty($dashboard, 'clock'))->getValue($dashboard));
    }
}
