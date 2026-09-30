<?php

declare(strict_types=1);

namespace unit\DurableLaravel;

use Gplanchat\Durable\Duration;
use Gplanchat\Durable\ExecutionId;
use Gplanchat\Durable\ExecutionRuntime;
use Gplanchat\Durable\Laravel\DurableServiceProvider;
use Gplanchat\Durable\Store\EventStoreInterface;
use Gplanchat\Durable\SystemClock;
use Gplanchat\Durable\Transport\ActivityMessage;
use Gplanchat\Durable\Transport\ActivityTransportInterface;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use unit\Durable\Fixtures\FrozenClock;

/**
 * Laravel has no PSR-20 clock of its own: the provider binds `durable.clock` to the core's system
 * clock, and an application rebinds it to read time elsewhere (#617).
 */
final class TheProviderWiresTheClockTest extends TestCase
{
    public function testByDefaultTheRuntimeReadsTheSystemClock(): void
    {
        self::assertInstanceOf(SystemClock::class, $this->registered()->make(ExecutionRuntime::class)->clock());
    }

    public function testARebindingReachesEveryServiceThatReadsTime(): void
    {
        $clock = new FrozenClock(1_700_000_000.0);
        $app = $this->registered();
        $app->instance('durable.clock', $clock);

        self::assertSame($clock, $app->make(ExecutionRuntime::class)->clock());

        $transport = $app->make(ActivityTransportInterface::class);
        $transport->enqueue(new ActivityMessage('exec-1', 'act-1', 'charge', [], retryDelay: Duration::seconds(5.0)));
        self::assertSame(1_700_000_005.0, $transport->nextDueAt());

        $journal = $app->make(EventStoreInterface::class);
        $journal->append(new \Gplanchat\Durable\Event\WorkflowSignalReceived(ExecutionId::fromString('exec-1'), 'go', []));
        foreach ($journal->readStreamWithRecordedAt(ExecutionId::fromString('exec-1')) as $row) {
            self::assertSame('1700000000', $row['recordedAt']->format('U'));
        }
    }

    public function testTheClockIsBoundByItsInterfaceAndTheStringIdIsItsAlias(): void
    {
        // #879: durable-filament resolves the clock by class; the string id stays for compatibility.
        $app = $this->registered();

        self::assertInstanceOf(SystemClock::class, $app->make(ClockInterface::class));
        self::assertSame($app->make(ClockInterface::class), $app->make('durable.clock'));
    }

    public function testAClockBoundByItsInterfaceReachesTheRuntime(): void
    {
        $clock = new FrozenClock(1_700_000_000.0);
        $app = $this->registered();
        $app->instance(ClockInterface::class, $clock);

        self::assertSame($clock, $app->make(ExecutionRuntime::class)->clock());
    }

    public function testAClockBoundUnderTheStringIdBeforeTheProviderIsTheOneTheInterfaceResolves(): void
    {
        $clock = new FrozenClock(1_700_000_000.0);
        $app = new Container();
        $app->instance('config', new \ArrayObject(['durable' => ['backend' => 'memory']], \ArrayObject::ARRAY_AS_PROPS));
        $app->instance('durable.clock', $clock);
        (new DurableServiceProvider($app))->register();

        self::assertSame($clock, $app->make(ClockInterface::class));
        self::assertSame($clock, $app->make(ExecutionRuntime::class)->clock());
    }

    private function registered(): Container
    {
        $app = new Container();
        $app->instance('config', new \ArrayObject(['durable' => ['backend' => 'memory']], \ArrayObject::ARRAY_AS_PROPS));
        (new DurableServiceProvider($app))->register();

        return $app;
    }
}
