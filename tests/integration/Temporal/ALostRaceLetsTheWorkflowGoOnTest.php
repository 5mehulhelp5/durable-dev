<?php

declare(strict_types=1);

namespace integration\Temporal;

/**
 * A timer that wins `any()` against an activity waiting out its backoff cancels it. The next
 * workflow task replays the race: the loser's ACTIVITY_TASK_CANCELED used to read back as a
 * rejection and win the race it had lost, and the loser's cancellation was sent again, which the
 * server refuses on an activity it already closed (#681, the Temporal side of #678).
 */
final class ALostRaceLetsTheWorkflowGoOnTest extends TemporalServerTestCase
{
    public function testTheWorkflowGoesOnAfterATimerBeatsARetryingActivity(): void
    {
        $executionId = $this->startWorkflow('LostRace', []);

        $result = $this->workflowClient()->pollForCompletion($executionId, 250, 120);

        self::assertSame(['winner' => 'timer', 'after' => true], $result);
        $events = $this->historyEventNames($executionId);
        self::assertNotContains('EVENT_TYPE_WORKFLOW_TASK_FAILED', $events, implode(', ', $events));
        self::assertCount(1, array_keys($events, 'EVENT_TYPE_ACTIVITY_TASK_CANCEL_REQUESTED', true), 'the loser is cancelled once');
    }
}
