<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable;

use Gplanchat\Durable\Activity\ActivityOptions;
use Gplanchat\Durable\Activity\ActivityTimeouts;
use Gplanchat\Durable\Activity\NullActivityHeartbeatSender;
use Gplanchat\Durable\Duration;
use Gplanchat\Durable\Event\ActivityCompleted;
use Gplanchat\Durable\Event\WorkflowSignalReceived;
use Gplanchat\Durable\Observation\WorkflowRunStatus;
use Gplanchat\Durable\Port\NoActivityAttemptClaim;
use Gplanchat\Durable\Port\NullWorkflowResumeDispatcher;
use Gplanchat\Durable\RegistryActivityExecutor;
use Gplanchat\Durable\Store\ActivityEventJournal;
use Gplanchat\Durable\Store\InMemoryEventStore;
use Gplanchat\Durable\Store\InMemoryWorkflowRunCatalog;
use Gplanchat\Durable\Transport\ActivityMessage;
use Gplanchat\Durable\Transport\InMemoryActivityTransport;
use Gplanchat\Durable\Uuid\NativeUuidV7Generator;
use Gplanchat\Durable\Worker\ActivityMessageProcessor;
use PHPUnit\Framework\TestCase;
use unit\Durable\Fixtures\FrozenClock;

/**
 * Every "now" the core stamps comes from the clock it was handed, not from the wall (#617).
 */
final class TheCoreReadsTimeFromItsClockTest extends TestCase
{
    public function testTheUuidV7TimestampIsTheClocksMillisecond(): void
    {
        $uuid = (new NativeUuidV7Generator(new FrozenClock(1_700_000_000.123)))->generate();

        self::assertSame(1_700_000_000_123, hexdec(str_replace('-', '', substr($uuid, 0, 13))));
    }

    public function testTheInMemoryJournalStampsTheClocksInstant(): void
    {
        $store = new InMemoryEventStore(new FrozenClock(1_700_000_000.5));
        $store->append(new WorkflowSignalReceived('exec-1', 'go', []));

        foreach ($store->readStreamWithRecordedAt('exec-1') as $row) {
            self::assertSame('1700000000.500000', $row['recordedAt']->format('U.u'));
        }
        self::assertSame(1, $store->countEventsInStream('exec-1'));
    }

    public function testTheInMemoryCatalogStampsTheClocksInstants(): void
    {
        $clock = new FrozenClock(1_700_000_000.0);
        $catalog = new InMemoryWorkflowRunCatalog(new InMemoryEventStore($clock), $clock);
        $catalog->recordStart('exec-1', 'Order');
        $clock->advance(42.0);
        $catalog->recordOutcome('exec-1', WorkflowRunStatus::Completed);

        $run = $catalog->findRun('exec-1');
        self::assertNotNull($run);
        self::assertSame('1700000000', $run->startedAt?->format('U'));
        self::assertSame('1700000042', $run->endedAt?->format('U'));
        self::assertSame('1700000042', $catalog->checkHealth()->checkedAt->format('U'));
    }

    public function testTheInMemoryTransportDefersARetryOnItsClock(): void
    {
        $clock = new FrozenClock(1_700_000_000.0);
        $transport = new InMemoryActivityTransport($clock);
        $transport->enqueue(new ActivityMessage('exec-1', 'act-1', 'charge', [], retryDelay: Duration::seconds(10.0)));

        self::assertSame(1_700_000_010.0, $transport->nextDueAt());
        self::assertNull($transport->dequeue());
        $clock->advance(10.0);
        self::assertNotNull($transport->dequeue());
    }

    public function testTheActivityTimeoutsAreMeasuredOnTheProcessorsClock(): void
    {
        $clock = new FrozenClock(1_700_000_005.0);
        $store = new InMemoryEventStore($clock);
        $executor = new RegistryActivityExecutor();
        $executor->register('charge', static fn(): string => 'ok');
        $processor = new ActivityMessageProcessor(
            $store,
            new InMemoryActivityTransport($clock),
            $executor,
            new NullWorkflowResumeDispatcher(),
            new NullActivityHeartbeatSender(),
            attemptClaim: new NoActivityAttemptClaim(),
            clock: $clock,
        );

        // Five seconds after the first queueing, by this clock: well inside the ten allowed.
        $processor->process(new ActivityMessage(
            'exec-1',
            'act-1',
            'charge',
            [],
            new ActivityOptions(timeouts: new ActivityTimeouts(scheduleToClose: Duration::seconds(10.0))),
            firstQueuedAt: 1_700_000_000.0,
        ));

        self::assertInstanceOf(ActivityCompleted::class, ActivityEventJournal::lastTerminalOutcome($store, 'exec-1', 'act-1'));
    }
}
