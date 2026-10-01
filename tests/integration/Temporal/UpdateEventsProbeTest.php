<?php

declare(strict_types=1);

namespace integration\Temporal;

use Temporal\Api\Enums\V1\EventType;

/**
 * Temporary diagnostic (#860): prints what the server writes in an update's accepted and completed
 * events. Removed before the PR is merged.
 */
final class UpdateEventsProbeTest extends TemporalServerTestCase
{
    public function testPrintTheUpdateEvents(): void
    {
        $executionId = $this->startWorkflow('Updatable', []);
        $this->workflowClient()->update($this->workflowId($executionId), 'approve', ['by' => 'alice'], 'upd-probe');

        $accepted = $this->waitForHistoryEvent($executionId, EventType::EVENT_TYPE_WORKFLOW_EXECUTION_UPDATE_ACCEPTED);
        $completed = $this->waitForHistoryEvent($executionId, EventType::EVENT_TYPE_WORKFLOW_EXECUTION_UPDATE_COMPLETED);

        fwrite(\STDERR, "\nPROBE accepted event " . $accepted->getEventId() . ': ' . $accepted->getWorkflowExecutionUpdateAcceptedEventAttributes()?->serializeToJsonString() . "\n");
        fwrite(\STDERR, 'PROBE completed event ' . $completed->getEventId() . ': ' . $completed->getWorkflowExecutionUpdateCompletedEventAttributes()?->serializeToJsonString() . "\n");

        self::assertTrue(true);
    }
}
