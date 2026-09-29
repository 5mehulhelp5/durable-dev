<?php

declare(strict_types=1);

namespace unit\Gplanchat\Bridge\Temporal\Store;

use Gplanchat\Bridge\Temporal\Codec\JsonPlainPayload;
use Gplanchat\Bridge\Temporal\Store\TemporalEventConverter;
use Gplanchat\Bridge\Temporal\Worker\TemporalExecutionHistory;
use Gplanchat\Durable\ActivityCancellationReason;
use Gplanchat\Durable\Event\ActivityCancelled;
use Gplanchat\Durable\Event\TimerCancelled;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\ActivityType;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\ActivityTaskCanceledEventAttributes;
use Temporal\Api\History\V1\ActivityTaskScheduledEventAttributes;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\History\V1\MarkerRecordedEventAttributes;
use Temporal\Api\History\V1\TimerCanceledEventAttributes;
use Temporal\Api\History\V1\TimerStartedEventAttributes;

/**
 * The server records one `*_CANCELED` event whatever the workflow cancelled for. The reason comes
 * from the history, by the rule `TemporalExecutionHistory` replays with (#694): an operation the
 * delivered-cancellation marker targets was withdrawn with the workflow, any other one lost a race.
 * The read model (event store, profiler, dashboards) then agrees with the event-store backends (#701).
 */
final class CancellationReasonConversionTest extends TestCase
{
    public function testACancelledActivityWithoutTheMarkerLostARace(): void
    {
        $converter = new TemporalEventConverter('exec-1');
        $converter->convert(self::activityScheduled(5, 'act-1'));

        $cancelled = $converter->convert(self::activityCanceled(9, 5));

        self::assertInstanceOf(ActivityCancelled::class, $cancelled);
        self::assertSame(ActivityCancellationReason::RACE_SUPERSEDED, $cancelled->reason());
    }

    public function testACancelledActivityTheMarkerTargetsWasCancelledWithTheWorkflow(): void
    {
        $converter = new TemporalEventConverter('exec-1');
        $converter->convert(self::activityScheduled(5, 'act-1'));
        $converter->convert(self::activityScheduled(6, 'act-2'));
        $converter->convert(self::cancellationDelivered(8, ['act-1']));

        $targeted = $converter->convert(self::activityCanceled(10, 5));
        $other = $converter->convert(self::activityCanceled(11, 6));

        self::assertInstanceOf(ActivityCancelled::class, $targeted);
        self::assertSame(ActivityCancellationReason::WORKFLOW_CANCELLED, $targeted->reason());
        self::assertInstanceOf(ActivityCancelled::class, $other);
        self::assertSame(ActivityCancellationReason::RACE_SUPERSEDED, $other->reason());
    }

    public function testACancelledTimerFollowsTheSameRule(): void
    {
        $converter = new TemporalEventConverter('exec-1');
        $converter->convert(self::timerStarted(5, 'timer-1'));
        $converter->convert(self::timerStarted(6, 'timer-2'));
        $converter->convert(self::cancellationDelivered(8, ['timer-1']));

        $targeted = $converter->convert(self::timerCanceled(10, 5));
        $other = $converter->convert(self::timerCanceled(11, 6));

        self::assertInstanceOf(TimerCancelled::class, $targeted);
        self::assertSame(ActivityCancellationReason::WORKFLOW_CANCELLED, $targeted->reason());
        self::assertInstanceOf(TimerCancelled::class, $other);
        self::assertSame(ActivityCancellationReason::RACE_SUPERSEDED, $other->reason());
    }

    private static function activityScheduled(int $eventId, string $activityId): HistoryEvent
    {
        $event = self::event($eventId, EventType::EVENT_TYPE_ACTIVITY_TASK_SCHEDULED);
        $event->setActivityTaskScheduledEventAttributes(new ActivityTaskScheduledEventAttributes([
            'activity_id' => $activityId,
            'activity_type' => new ActivityType(['name' => 'quote']),
        ]));

        return $event;
    }

    private static function activityCanceled(int $eventId, int $scheduledEventId): HistoryEvent
    {
        $event = self::event($eventId, EventType::EVENT_TYPE_ACTIVITY_TASK_CANCELED);
        $event->setActivityTaskCanceledEventAttributes(new ActivityTaskCanceledEventAttributes(['scheduled_event_id' => $scheduledEventId]));

        return $event;
    }

    private static function timerStarted(int $eventId, string $timerId): HistoryEvent
    {
        $event = self::event($eventId, EventType::EVENT_TYPE_TIMER_STARTED);
        $event->setTimerStartedEventAttributes(new TimerStartedEventAttributes(['timer_id' => $timerId]));

        return $event;
    }

    private static function timerCanceled(int $eventId, int $startedEventId): HistoryEvent
    {
        $event = self::event($eventId, EventType::EVENT_TYPE_TIMER_CANCELED);
        $event->setTimerCanceledEventAttributes(new TimerCanceledEventAttributes(['started_event_id' => $startedEventId]));

        return $event;
    }

    /**
     * @param list<string> $targets
     */
    private static function cancellationDelivered(int $eventId, array $targets): HistoryEvent
    {
        $event = self::event($eventId, EventType::EVENT_TYPE_MARKER_RECORDED);
        $event->setMarkerRecordedEventAttributes(new MarkerRecordedEventAttributes([
            'marker_name' => TemporalExecutionHistory::MARKER_CANCELLATION_DELIVERED,
            'details' => ['targets' => JsonPlainPayload::singlePayloads(JsonPlainPayload::encode($targets))],
        ]));

        return $event;
    }

    private static function event(int $eventId, int $type): HistoryEvent
    {
        $event = new HistoryEvent();
        $event->setEventId($eventId);
        $event->setEventType($type);

        return $event;
    }
}
