<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable\Worker;

use Gplanchat\Durable\Event\ActivityCancelled;
use Gplanchat\Durable\Event\ActivityCompleted;
use Gplanchat\Durable\Event\ActivityTaskFailed;
use Gplanchat\Durable\ExecutionId;
use Gplanchat\Durable\Port\ActivityHeartbeatSenderInterface;
use Gplanchat\Durable\Port\WorkflowResumeDispatcher;
use Gplanchat\Durable\RegistryActivityExecutor;
use Gplanchat\Durable\Store\InMemoryEventStore;
use Gplanchat\Durable\Transport\ActivityMessage;
use Gplanchat\Durable\Transport\InMemoryActivityTransport;
use Gplanchat\Durable\Worker\ActivityMessageProcessor;
use PHPUnit\Framework\TestCase;

/**
 * #328: the resume was sent inside the attempt's `try`, so a broker that refused it had the attempt
 * journalled as failed, when it had succeeded, and could spend its retries. A send that fails now
 * fails the message, which the transport redelivers; the attempt's outcome stays what it was.
 */
final class AFailedResumeSendIsNotAFailedActivityTest extends TestCase
{
    public function testASucceededAttemptStaysCompletedWhenTheResumeCannotBeSent(): void
    {
        [$store, $transport, $processor] = $this->processor(cancelled: false);

        try {
            $processor->process(new ActivityMessage('exec-1', 'act-1', 'charge', []));
            self::fail('the broker error must reach the transport, which redelivers the message');
        } catch (\RuntimeException $e) {
            self::assertSame('broker down', $e->getMessage());
        }

        self::assertSame(1, $this->eventsOf($store, ActivityCompleted::class));
        self::assertSame(0, $this->eventsOf($store, ActivityTaskFailed::class), 'the attempt did not fail');
        self::assertNull($transport->dequeue(), 'and no retry was scheduled for it');
    }

    public function testACancelledAttemptStaysCancelledWhenTheResumeCannotBeSent(): void
    {
        [$store, , $processor] = $this->processor(cancelled: true);

        $this->expectExceptionMessage('broker down');

        try {
            $processor->process(new ActivityMessage('exec-1', 'act-1', 'charge', []));
        } finally {
            self::assertSame(1, $this->eventsOf($store, ActivityCancelled::class));
            self::assertSame(0, $this->eventsOf($store, ActivityTaskFailed::class));
        }
    }

    /**
     * The redelivery the fix relies on meets the #319 guard first: the outcome is journalled, so the
     * attempt is not run again. The guard must still send the resume the first delivery lost, or
     * the run waits forever with its result in the journal (at-least-once, #328).
     */
    public function testTheRedeliverySendsTheResumeTheFirstDeliveryLost(): void
    {
        $store = new InMemoryEventStore();
        $executor = new RegistryActivityExecutor();
        $executor->register('charge', static fn(): string => 'ch_1');
        // The broker refuses the first send only; every later one goes through.
        $calls = 0;
        $resumes = $this->createStub(WorkflowResumeDispatcher::class);
        $resumes->method('dispatchResume')->willReturnCallback(static function () use (&$calls): void {
            if (1 === ++$calls) {
                throw new \RuntimeException('broker down');
            }
        });
        $processor = new ActivityMessageProcessor($store, new InMemoryActivityTransport(), $executor, $resumes, $this->createStub(ActivityHeartbeatSenderInterface::class));
        $message = new ActivityMessage('exec-1', 'act-1', 'charge', []);

        try {
            $processor->process($message);
        } catch (\RuntimeException) {
        }
        $processor->process($message);

        self::assertSame(2, $calls, 'the redelivery sends the resume the first delivery lost');
        self::assertSame(1, $this->eventsOf($store, ActivityCompleted::class), 'without running the attempt again');
    }

    /**
     * @return array{InMemoryEventStore, InMemoryActivityTransport, ActivityMessageProcessor}
     */
    private function processor(bool $cancelled): array
    {
        $store = new InMemoryEventStore();
        $transport = new InMemoryActivityTransport();
        $executor = new RegistryActivityExecutor();
        $executor->register('charge', static fn(): string => 'ch_1');
        $resumes = $this->createStub(WorkflowResumeDispatcher::class);
        $resumes->method('dispatchResume')->willThrowException(new \RuntimeException('broker down'));
        $heartbeat = $this->createStub(ActivityHeartbeatSenderInterface::class);
        $heartbeat->method('isCancellationRequested')->willReturn($cancelled);

        return [$store, $transport, new ActivityMessageProcessor($store, $transport, $executor, $resumes, $heartbeat)];
    }

    /**
     * @param class-string $class
     */
    private function eventsOf(InMemoryEventStore $store, string $class): int
    {
        return \count(array_filter(iterator_to_array($store->readStream(ExecutionId::fromString('exec-1')), false), static fn(object $e): bool => $e instanceof $class));
    }
}
