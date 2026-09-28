<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable\Bundle\Messenger;

use Gplanchat\Durable\Attribute\AsWorkflow;
use Gplanchat\Durable\Attribute\AsWorkflowMethod;
use Gplanchat\Durable\Bundle\Messenger\MessengerWorkflowResumeDispatcher;
use Gplanchat\Durable\Bundle\Messenger\NewWorkflowRunStamp;
use Gplanchat\Durable\Store\InMemoryWorkflowMetadataStore;
use Gplanchat\Durable\Transport\ResumeWorkflowMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;

#[AsWorkflow(name: 'test.order-fulfilment')]
final class OrderFulfilmentWorkflow
{
    #[AsWorkflowMethod]
    public function run(): void {}
}

final class MessengerWorkflowResumeDispatcherTest extends TestCase
{
    /** #258: a caller passing `::class` gets the alias in the metadata the diagnose command and the profiler show. */
    public function testANewRunStartedByClassIsRecordedUnderItsAlias(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<Envelope> */
            public array $sent = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->sent[] = Envelope::wrap($message, $stamps);
            }
        };
        $metadata = new InMemoryWorkflowMetadataStore();

        (new MessengerWorkflowResumeDispatcher($bus, $metadata))
            ->dispatchNewWorkflowRun('order-1', OrderFulfilmentWorkflow::class, []);

        self::assertSame('test.order-fulfilment', $metadata->get('order-1')['workflowType'] ?? null);
        self::assertSame('test.order-fulfilment', $bus->sent[0]->last(NewWorkflowRunStamp::class)?->workflowType);
    }

    /**
     * DUR050: the resume sent before the outcome leaves at once. Held by DispatchAfterCurrentBusStamp
     * until the activity handler returns, it would leave after the append, and the order would be
     * cosmetic.
     */
    public function testAnAnnouncingResumeLeavesAtOnceAndNamesItsActivity(): void
    {
        $bus = new RecordingBus();

        (new MessengerWorkflowResumeDispatcher($bus, new InMemoryWorkflowMetadataStore(), $this->routedTo(new InMemoryTransport())))
            ->dispatchResumeAnnouncing('exec-1', 'act-1');

        self::assertCount(1, $bus->sent);
        self::assertNull($bus->sent[0]->last(DispatchAfterCurrentBusStamp::class));
        $message = $bus->sent[0]->getMessage();
        self::assertInstanceOf(ResumeWorkflowMessage::class, $message);
        self::assertSame('act-1', $message->awaitedActivityId);
    }

    /**
     * Routed `sync`, the resume would run inline, before the append, every time: nothing is sent
     * early, and the send after the append does the work (DUR050, choice 2).
     */
    public function testAnAnnouncingResumeRoutedSyncIsNotSent(): void
    {
        $bus = new RecordingBus();

        (new MessengerWorkflowResumeDispatcher($bus, new InMemoryWorkflowMetadataStore(), $this->routedTo($this->createStub(SyncTransport::class))))
            ->dispatchResumeAnnouncing('exec-1', 'act-1');

        self::assertSame([], $bus->sent);
    }

    private function routedTo(object $sender): SendersLocatorInterface
    {
        return new class ($sender) implements SendersLocatorInterface {
            public function __construct(private readonly object $sender) {}

            public function getSenders(Envelope $envelope): iterable
            {
                yield 'durable_workflows' => $this->sender;
            }
        };
    }
}

final class RecordingBus implements MessageBusInterface
{
    /** @var list<Envelope> */
    public array $sent = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        return $this->sent[] = Envelope::wrap($message, $stamps);
    }
}
