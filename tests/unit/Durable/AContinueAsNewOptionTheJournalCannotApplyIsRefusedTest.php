<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable;

use Gplanchat\Durable\ContinueAsNewOptions;
use Gplanchat\Durable\Duration;
use Gplanchat\Durable\Event\WorkflowContinuedAsNew;
use Gplanchat\Durable\Exception\ContinueAsNewRequested;
use Gplanchat\Durable\Exception\UnsupportedByBackendException;
use Gplanchat\Durable\ExecutionId;
use Gplanchat\Durable\Store\EventStoreWorkflowLifecycle;
use Gplanchat\Durable\Store\InMemoryEventStore;
use Gplanchat\Durable\TaskQueue;
use Gplanchat\Durable\WorkflowTimeouts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The journal backends (InMemory, DBAL, Illuminate) record a continue-as-new but have no task queue
 * to move to and no timer for the run bounds. They refuse those options by name (#977); Temporal
 * applies them (see TemporalWorkflowFailureRoundTripTest).
 */
final class AContinueAsNewOptionTheJournalCannotApplyIsRefusedTest extends TestCase
{
    /** @return iterable<string, array{ContinueAsNewOptions, string}> */
    public static function unsupportedOptions(): iterable
    {
        yield 'task queue' => [new ContinueAsNewOptions(taskQueue: TaskQueue::named('next-queue')), 'ContinueAsNewOptions::$taskQueue'];
        yield 'run timeout' => [new ContinueAsNewOptions(timeouts: new WorkflowTimeouts(run: Duration::minutes(5))), 'ContinueAsNewOptions::$timeouts->run'];
        yield 'task timeout' => [new ContinueAsNewOptions(timeouts: new WorkflowTimeouts(task: Duration::seconds(30))), 'ContinueAsNewOptions::$timeouts->task'];
    }

    #[DataProvider('unsupportedOptions')]
    public function testTheOptionIsRefusedAndNothingIsJournaled(ContinueAsNewOptions $options, string $name): void
    {
        $store = new InMemoryEventStore();
        $id = ExecutionId::generate();

        try {
            (new EventStoreWorkflowLifecycle($store))->onContinuedAsNew($id, new ContinueAsNewRequested('Next', [], $options));
            self::fail('The journal lifecycle must refuse the option.');
        } catch (UnsupportedByBackendException $refusal) {
            self::assertStringContainsString($name, $refusal->getMessage());
            self::assertStringContainsString('journal', $refusal->getMessage());
        }

        self::assertSame([], iterator_to_array($store->readStream($id), false));
    }

    public function testNoOptionAndEmptyOptionsAreAccepted(): void
    {
        foreach ([null, ContinueAsNewOptions::new()] as $options) {
            $store = new InMemoryEventStore();
            $id = ExecutionId::generate();

            try {
                (new EventStoreWorkflowLifecycle($store))->onContinuedAsNew($id, new ContinueAsNewRequested('Next', [], $options));
                self::fail('A continue-as-new always ends in its request.');
            } catch (ContinueAsNewRequested) {
            }

            self::assertInstanceOf(WorkflowContinuedAsNew::class, iterator_to_array($store->readStream($id), false)[0]);
        }
    }
}
