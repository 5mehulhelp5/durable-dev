<?php

declare(strict_types=1);

namespace integration\Temporal;

use Gplanchat\Bridge\Temporal\Grpc\TemporalHistoryCursor;
use Gplanchat\Durable\Exception\DurableUpdateFailedException;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Enums\V1\EventType;

/**
 * Temporary diagnostic (#860): prints what the server writes for updates, first while the
 * workflow keeps running (a failed handler), then once an update ends it. Removed before merge.
 */
final class UpdateEventsProbeTest extends TemporalServerTestCase
{
    public function testPrintTheUpdateEvents(): void
    {
        $executionId = $this->startWorkflow('Updatable', []);
        $workflowId = $this->workflowId($executionId);

        try {
            $this->workflowClient()->update($workflowId, 'refuse', ['by' => 'bob'], 'upd-probe-refuse');
        } catch (DurableUpdateFailedException) {
        }
        sleep(2);
        $this->dump('after refuse (still running)', $workflowId);

        $this->workflowClient()->update($workflowId, 'approve', ['by' => 'alice'], 'upd-probe-approve');
        $this->workflowClient()->pollForCompletion($executionId, 250, 160);
        $this->dump('after approve (completed)', $workflowId);

        self::assertTrue(true);
    }

    private function dump(string $moment, string $workflowId): void
    {
        $cursor = new TemporalHistoryCursor($this->client, $this->connection);
        fwrite(\STDERR, "\nPROBE " . $moment . "\n");
        foreach ($cursor->events(new WorkflowExecution(['workflow_id' => $workflowId])) as $event) {
            $attributes = $event->getWorkflowExecutionUpdateAcceptedEventAttributes() ?? $event->getWorkflowExecutionUpdateCompletedEventAttributes();
            fwrite(\STDERR, \sprintf("PROBE %d %s %s\n", $event->getEventId(), EventType::name($event->getEventType()), $attributes?->serializeToJsonString() ?? ''));
        }
    }
}
