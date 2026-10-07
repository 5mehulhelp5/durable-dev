<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable;

use Gplanchat\Durable\ChildWorkflowOptions;
use Gplanchat\Durable\CronSchedule;
use Gplanchat\Durable\Exception\UnsupportedByBackendException;
use Gplanchat\Durable\ExecutionId;
use Gplanchat\Durable\Store\EventStoreCommandBuffer;
use Gplanchat\Durable\Store\InMemoryEventStore;
use Gplanchat\Durable\TaskQueue;
use Gplanchat\Durable\Transport\NoopActivityTransport;
use Gplanchat\Durable\WorkflowNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The journal backends (in-memory, DBAL, Illuminate) record a child's namespace, task queue and
 * cron schedule without applying them. They refuse them by name instead (#977).
 */
final class ChildWorkflowOptionsRefusedByTheJournalBackendTest extends TestCase
{
    /**
     * @return iterable<string, array{ChildWorkflowOptions, string}>
     */
    public static function unsupportedOptions(): iterable
    {
        yield 'namespace' => [new ChildWorkflowOptions(namespace: WorkflowNamespace::named('billing')), 'namespace'];
        yield 'task queue' => [new ChildWorkflowOptions(taskQueue: TaskQueue::named('billing-queue')), 'taskQueue'];
        yield 'cron schedule' => [new ChildWorkflowOptions(cronSchedule: CronSchedule::hourly()), 'cronSchedule'];
    }

    #[DataProvider('unsupportedOptions')]
    public function testTheOptionIsRefusedByNameAndNothingIsJournaled(ChildWorkflowOptions $options, string $name): void
    {
        $store = new InMemoryEventStore();
        $buffer = new EventStoreCommandBuffer($store, new NoopActivityTransport(), ExecutionId::fromString('parent-1'));

        try {
            $buffer->scheduleChildWorkflow(ExecutionId::fromString('child-1'), 'ChildType', [], $options);
            self::fail(\sprintf('ChildWorkflowOptions::$%s must be refused', $name));
        } catch (UnsupportedByBackendException $refusal) {
            self::assertStringContainsString(\sprintf('ChildWorkflowOptions::$%s', $name), $refusal->getMessage());
            self::assertStringContainsString('journal', $refusal->getMessage());
        }
        self::assertCount(0, iterator_to_array($store->readStream(ExecutionId::fromString('parent-1')), false));
    }

    public function testTheOtherOptionsStillPass(): void
    {
        $buffer = new EventStoreCommandBuffer(new InMemoryEventStore(), new NoopActivityTransport(), ExecutionId::fromString('parent-1'));

        $buffer->scheduleChildWorkflow(ExecutionId::fromString('child-1'), 'ChildType', [], new ChildWorkflowOptions(memo: ['k' => 'v']));

        $this->addToAssertionCount(1);
    }
}
