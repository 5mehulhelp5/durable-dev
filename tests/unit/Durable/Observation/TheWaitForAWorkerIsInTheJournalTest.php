<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable\Observation;

use Gplanchat\Durable\Event\ExecutionStarted;
use Gplanchat\Durable\Event\WorkflowTaskCompleted;
use Gplanchat\Durable\Event\WorkflowTaskScheduled;
use Gplanchat\Durable\Event\WorkflowTaskStarted;
use Gplanchat\Durable\ExecutionId;
use Gplanchat\Durable\Observation\JournalRunHistoryReader;
use Gplanchat\Durable\Observation\WorkflowRunEventKind;
use Gplanchat\Durable\Store\InMemoryEventStore;
use PHPUnit\Framework\TestCase;

/**
 * The gap between a dispatch and the pass that answers it is a queue, and the journal now says so
 * with the events Temporal records for it (#850 item 3).
 */
final class TheWaitForAWorkerIsInTheJournalTest extends TestCase
{
    public function testTheTaskEventsBelongToTheRunAndOnlyItsStartIsAPickup(): void
    {
        $id = ExecutionId::fromString('exec-1');
        $store = new InMemoryEventStore();
        foreach ([
            new WorkflowTaskScheduled($id),
            new WorkflowTaskStarted($id),
            new ExecutionStarted($id, []),
            new WorkflowTaskCompleted($id),
        ] as $event) {
            $store->append($event);
        }

        $history = (new JournalRunHistoryReader($store))->read('exec-1');

        self::assertSame(['workflow', 'workflow', 'workflow', 'workflow'], array_column($history, 'actionKey'));
        self::assertSame([false, true, false, false], array_column($history, 'started'));
        self::assertSame(['requested', 'started', 'started', 'settled'], array_map(static fn($e): ?string => $e->phase?->value, $history));
        self::assertSame(WorkflowRunEventKind::Execution, $history[0]->kind);
    }
}
