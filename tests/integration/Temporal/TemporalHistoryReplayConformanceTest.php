<?php

declare(strict_types=1);

namespace integration\Temporal;

use Gplanchat\Bridge\Temporal\Grpc\TemporalHistoryCursor;
use Gplanchat\Bridge\Temporal\Worker\TemporalExecutionHistory;
use Gplanchat\Durable\InMemoryWorkflowRunner;
use Gplanchat\Durable\Port\WorkflowHistorySourceInterface;
use Gplanchat\Durable\RegistryActivityExecutor;
use Gplanchat\Durable\Store\EventStoreHistorySource;
use Gplanchat\Durable\Store\InMemoryEventStore;
use Gplanchat\Durable\Testing\ConformanceChildWorkflow;
use Gplanchat\Durable\Testing\ConformanceWorkflow;
use Gplanchat\Durable\Transport\InMemoryActivityTransport;
use Gplanchat\Durable\WorkflowRegistry;
use Temporal\Api\Common\V1\WorkflowExecution;

/**
 * DUR041's replay tier on Temporal (#326). The same conformance workflow runs on a server, through
 * this suite's workers, and inline on the in-memory reference; every lookup of the history port
 * must then read the same thing from `TemporalExecutionHistory` as from the reference.
 *
 * Positions are backend-specific (the stream index in memory, the `eventId` on Temporal) and never
 * compared across backends (DUR035): only whether one is found is.
 *
 * @see DUR041
 */
final class TemporalHistoryReplayConformanceTest extends TemporalServerTestCase
{
    public function testEveryHistoryLookupAgreesWithTheReference(): void
    {
        $executionId = $this->startWorkflow(ConformanceWorkflow::TYPE, []);
        $onServer = $this->workflowClient()->pollForCompletion($executionId, 250, 240);

        $reference = new InMemoryEventStore();
        $inMemory = self::runOnTheReference($reference);
        self::assertEquals($inMemory, $onServer, 'the workflow returns the same result on both backends');

        $cursor = new TemporalHistoryCursor($this->client, $this->connection);
        $subject = TemporalExecutionHistory::fromEvents($cursor->events(new WorkflowExecution(['workflow_id' => $this->workflowId($executionId)])));

        self::assertHistoriesAgree(new EventStoreHistorySource($reference, 'exec-reference'), $subject);
    }

    private static function assertHistoriesAgree(WorkflowHistorySourceInterface $reference, WorkflowHistorySourceInterface $subject): void
    {
        // Activity: one slot, its outcome, its identity and its payload.
        self::assertEquals($reference->findActivitySlotResult(0)?->result, $subject->findActivitySlotResult(0)?->result);
        self::assertNull($subject->findActivitySlotResult(0)?->failed);
        self::assertNotNull($subject->findScheduledActivityId(0));
        self::assertNull($subject->findScheduledActivityId(1));
        self::assertNull($subject->findActivitySlotResult(1));
        self::assertSame($reference->activityNameForSlot(0), $subject->activityNameForSlot(0));
        self::assertNotNull($subject->activityNameForSlot(0));
        self::assertEquals($reference->activityPayloadForSlot(0), $subject->activityPayloadForSlot(0));

        // Version: the recorded one, and none for a change point never declared.
        self::assertSame(1, $subject->versionForChangeId('conformance-change'));
        self::assertNull($subject->versionForChangeId('never-declared'));

        // Timer: it fired, and its firing is found (where is backend-specific).
        $timerId = $subject->findScheduledTimerId(0);
        self::assertNotNull($timerId);
        self::assertNotNull($subject->findTimerSlotResult(0));
        self::assertNull($subject->findTimerSlotResult(0)->failed);
        self::assertNotNull($subject->timerCompletionPosition($timerId));

        // Side effects: two, their values through a JSON round trip, and no third.
        foreach ([0, 1, 2] as $slot) {
            self::assertSame($reference->hasSideEffectForSlot($slot), $subject->hasSideEffectForSlot($slot), "side-effect slot {$slot}");
            self::assertEquals($reference->findSideEffectForSlot($slot)?->result, $subject->findSideEffectForSlot($slot)?->result, "side-effect slot {$slot}");
        }

        // Child: its type, its input, its result.
        $childId = $subject->findScheduledChildExecutionId(0);
        self::assertNotNull($childId);
        self::assertSame($reference->childWorkflowTypeForSlot(0), $subject->childWorkflowTypeForSlot(0));
        self::assertEquals($reference->childWorkflowInputForSlot(0), $subject->childWorkflowInputForSlot(0));
        self::assertEquals($reference->findChildWorkflowForSlot(0)?->result, $subject->findChildWorkflowForSlot(0)?->result);
        self::assertSame($childId, $subject->findChildWorkflowForSlot(0)?->childExecutionId);
        self::assertNull($subject->childWorkflowTypeForSlot(1));

        // What this workflow never does reads back as absent on both sides.
        foreach ([$reference, $subject] as $history) {
            self::assertNull($history->messageAt(0));
            self::assertNull($history->findScheduledNexusOperation(0));
            self::assertNull($history->findNexusOperationSlotResult(0));
            self::assertNull($history->nexusOperationSignatureForSlot(0));
            self::assertNull($history->nexusOperationPayloadForSlot(0));
            self::assertNull($history->cancellationDelivery());
        }
    }

    private static function runOnTheReference(InMemoryEventStore $store): mixed
    {
        $activities = new RegistryActivityExecutor();
        ConformanceWorkflow::registerActivity($activities);
        $registry = new WorkflowRegistry();
        $registry->registerClass(ConformanceChildWorkflow::class);

        return (new InMemoryWorkflowRunner($store, new InMemoryActivityTransport(), $activities, 0, $registry))
            ->run('exec-reference', ConformanceWorkflow::run(...));
    }
}
