<?php

declare(strict_types=1);

namespace unit\Gplanchat\Bridge\Temporal\Worker;

use Google\Protobuf\Duration as ProtobufDuration;
use Google\Protobuf\Timestamp;
use Gplanchat\Bridge\Temporal\Codec\JsonPlainPayload;
use Gplanchat\Bridge\Temporal\Grpc\TemporalHistoryCursor;
use Gplanchat\Bridge\Temporal\Journal\JournalExecutionIdResolver;
use Gplanchat\Bridge\Temporal\TemporalConnection;
use Gplanchat\Bridge\Temporal\Worker\WorkflowTaskRunner;
use Gplanchat\Bridge\Temporal\WorkflowServiceClientInterface;
use Gplanchat\Durable\WorkflowEnvironment;
use Gplanchat\Durable\WorkflowRegistry;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Command\V1\Command;
use Temporal\Api\Common\V1\ActivityType;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Api\Enums\V1\CommandType;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\ActivityTaskScheduledEventAttributes;
use Temporal\Api\History\V1\History;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\History\V1\TimerStartedEventAttributes;
use Temporal\Api\History\V1\WorkflowExecutionStartedEventAttributes;
use Temporal\Api\Workflowservice\V1\PollWorkflowTaskQueueResponse;
use unit\Durable\Fixtures\SuiteActivities;

/**
 * #514: a suspending task words its wait as the core does ({@see \Gplanchat\Durable\Observation\WaitReason}),
 * whether the activity or the timer was scheduled in this task or in an earlier one.
 */
final class TheWorkerWordsTheWaitTest extends TestCase
{
    public function testAnActivityScheduledInThisTaskIsNamed(): void
    {
        $commands = $this->task('ActivityWorkflow', [self::started(1)]);

        self::assertSame(CommandType::COMMAND_TYPE_SCHEDULE_ACTIVITY_TASK, $commands[0]->getCommandType(), 'the memo does not push the schedule out of its slot');
        self::assertSame('activity greet', self::waitIn($commands));
    }

    public function testAnActivityScheduledInAnEarlierTaskIsStillNamed(): void
    {
        $commands = $this->task('ActivityWorkflow', [self::started(1), self::activityScheduled(5, 'greet')]);

        self::assertSame('activity greet', self::waitIn($commands));
        self::assertCount(1, $commands, 'no second schedule on replay');
    }

    public function testATimerIsDueAtItsStartPlusItsTimeout(): void
    {
        $commands = $this->task('TimerWorkflow', [self::started(1), self::timerStarted(5, 1_790_000_000, 60)]);

        self::assertSame('timer due at ' . (new \DateTimeImmutable('@1790000060'))->format(\DATE_ATOM), self::waitIn($commands));
    }

    public function testAConditionIsNamedByItsLabel(): void
    {
        self::assertSame('the stock comes back', self::waitIn($this->task('ConditionWorkflow', [self::started(1)])));
    }

    /**
     * @param list<HistoryEvent> $events
     *
     * @return list<Command>
     */
    private function task(string $workflow, array $events): array
    {
        $registry = new WorkflowRegistry();
        $registry->registerFactory('ActivityWorkflow', static fn(array $payload) => static fn(WorkflowEnvironment $env): string => $env->await($env->activityStub(SuiteActivities::class)->greet('World')));
        $registry->registerFactory('TimerWorkflow', static fn(array $payload) => static function (WorkflowEnvironment $env): string {
            $env->sleep(60);

            return 'after';
        });
        $registry->registerFactory('ConditionWorkflow', static fn(array $payload) => static fn(WorkflowEnvironment $env): mixed => $env->await(static fn(): bool => false, label: 'the stock comes back'));

        $history = new History();
        $history->setEvents($events);
        $poll = new PollWorkflowTaskQueueResponse();
        $poll->setTaskToken('token');
        $poll->setWorkflowExecution(new WorkflowExecution(['workflow_id' => 'wf-1']));
        $poll->setWorkflowType(new WorkflowType(['name' => $workflow]));
        $poll->setHistory($history);

        $connection = new TemporalConnection('localhost:7233', 'test-namespace');
        $runner = new WorkflowTaskRunner(new TemporalHistoryCursor($this->createMock(WorkflowServiceClientInterface::class), 'test-namespace'), $registry, $connection);

        return array_values($runner->run($poll)->commands);
    }

    /**
     * @param list<Command> $commands
     */
    private static function waitIn(array $commands): ?string
    {
        foreach ($commands as $command) {
            $field = $command->getModifyWorkflowPropertiesCommandAttributes()?->getUpsertedMemo()?->getFields()[JournalExecutionIdResolver::MEMO_KEY_DURABLE_WAITING_ON] ?? null;
            if (null !== $field) {
                $waitingOn = JsonPlainPayload::decode($field);
                self::assertTrue(null === $waitingOn || \is_string($waitingOn));

                return $waitingOn;
            }
        }
        self::fail('no durableWaitingOn memo in the task');
    }

    private static function started(int $id): HistoryEvent
    {
        $input = new Payloads();
        $input->setPayloads([JsonPlainPayload::encode([])]);

        return self::event($id, EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED)
            ->setWorkflowExecutionStartedEventAttributes(new WorkflowExecutionStartedEventAttributes(['input' => $input]));
    }

    private static function activityScheduled(int $id, string $name): HistoryEvent
    {
        return self::event($id, EventType::EVENT_TYPE_ACTIVITY_TASK_SCHEDULED)
            ->setActivityTaskScheduledEventAttributes(new ActivityTaskScheduledEventAttributes(['activity_id' => 'act-1', 'activity_type' => new ActivityType(['name' => $name])]));
    }

    private static function timerStarted(int $id, int $at, int $seconds): HistoryEvent
    {
        return self::event($id, EventType::EVENT_TYPE_TIMER_STARTED)
            ->setEventTime(new Timestamp(['seconds' => $at]))
            ->setTimerStartedEventAttributes(new TimerStartedEventAttributes(['timer_id' => 'timer-1', 'start_to_fire_timeout' => new ProtobufDuration(['seconds' => $seconds])]));
    }

    private static function event(int $id, int $type): HistoryEvent
    {
        return new HistoryEvent(['event_id' => $id, 'event_type' => $type]);
    }
}
