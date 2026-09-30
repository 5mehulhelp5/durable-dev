<?php

declare(strict_types=1);

namespace unit\Gplanchat\Bridge\Temporal\Codec;

use Gplanchat\Bridge\Temporal\Codec\PayloadCodecInterface;
use Gplanchat\Bridge\Temporal\Codec\PayloadCodecWorkflowServiceClient;
use Gplanchat\Bridge\Temporal\Grpc\TemporalHistoryCursor;
use Gplanchat\Bridge\Temporal\Grpc\WorkflowServiceActivityRpc;
use Gplanchat\Bridge\Temporal\TemporalConnection;
use Gplanchat\Bridge\Temporal\Worker\TemporalActivityWorker;
use Gplanchat\Bridge\Temporal\Worker\WorkflowTaskProcessor;
use Gplanchat\Bridge\Temporal\Worker\WorkflowTaskRunner;
use Gplanchat\Bridge\Temporal\WorkflowServiceClientInterface;
use Gplanchat\Durable\Port\ActivityHeartbeatSenderInterface;
use Gplanchat\Durable\Port\NullWorkflowResumeDispatcher;
use Gplanchat\Durable\RegistryActivityExecutor;
use Gplanchat\Durable\Store\InMemoryEventStore;
use Gplanchat\Durable\Transport\NoopActivityTransport;
use Gplanchat\Durable\Worker\ActivityMessageProcessor;
use Gplanchat\Durable\WorkflowRegistry;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Enums\V1\ActivityTaskFailedCause;
use Temporal\Api\Enums\V1\WorkflowTaskFailedCause;
use Temporal\Api\History\V1\History;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\History\V1\WorkflowExecutionStartedEventAttributes;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryRequest;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryResponse;
use Temporal\Api\Workflowservice\V1\PollActivityTaskQueueRequest;
use Temporal\Api\Workflowservice\V1\PollActivityTaskQueueResponse;
use Temporal\Api\Workflowservice\V1\PollWorkflowTaskQueueResponse;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedResponse;
use Temporal\Api\Workflowservice\V1\RespondWorkflowTaskFailedRequest;
use Temporal\Api\Workflowservice\V1\RespondWorkflowTaskFailedResponse;

/**
 * #775: a task whose payload the codec cannot decode is answered as failed, and the worker polls
 * again instead of dying on it — then dying again on the same task in the next worker.
 */
final class UndecodableTaskTest extends TestCase
{
    public function testAnUndecodableWorkflowTaskIsAnsweredAsFailedAndTheLoopPollsAgain(): void
    {
        $started = new WorkflowExecutionStartedEventAttributes(['input' => self::undecodable()]);
        $task = new PollWorkflowTaskQueueResponse([
            'task_token' => 'wf-token',
            'history' => new History(['events' => [new HistoryEvent(['workflow_execution_started_event_attributes' => $started])]]),
        ]);
        $failed = null;
        $inner = $this->createMock(WorkflowServiceClientInterface::class);
        $inner->expects(self::exactly(2))->method('PollWorkflowTaskQueue')
            ->willReturnOnConsecutiveCalls($task, new PollWorkflowTaskQueueResponse());
        $inner->expects(self::once())->method('RespondWorkflowTaskFailed')->willReturnCallback(static function (RespondWorkflowTaskFailedRequest $request) use (&$failed): RespondWorkflowTaskFailedResponse {
            $failed = $request;

            return new RespondWorkflowTaskFailedResponse();
        });
        $client = new PayloadCodecWorkflowServiceClient($inner, new FailingCodec());
        $connection = new TemporalConnection('localhost:7233', 'test-namespace');
        $runner = new WorkflowTaskRunner(new TemporalHistoryCursor($client, 'test-namespace'), new WorkflowRegistry(), $connection);

        $polls = 0;
        (new WorkflowTaskProcessor($client, $connection, $runner))->run(static function () use (&$polls): bool {
            return ++$polls < 2;
        });

        self::assertInstanceOf(RespondWorkflowTaskFailedRequest::class, $failed);
        self::assertSame('wf-token', $failed->getTaskToken());
        self::assertSame('test-namespace', $failed->getNamespace());
        self::assertStringContainsString('unknown key k2', (string) $failed->getFailure()?->getMessage());
        self::assertSame('', $failed->getFailure()?->getStackTrace(), 'a stack trace may quote key material or plaintext');
        self::assertSame(WorkflowTaskFailedCause::WORKFLOW_TASK_FAILED_CAUSE_WORKFLOW_WORKER_UNHANDLED_FAILURE, $failed->getCause());
    }

    public function testAnUndecodableActivityTaskIsAnsweredAsFailedAndTheWorkerPollsAgain(): void
    {
        $task = new PollActivityTaskQueueResponse(['task_token' => 'act-token', 'input' => self::undecodable()]);
        $failed = null;
        $inner = $this->createMock(WorkflowServiceClientInterface::class);
        $inner->expects(self::exactly(2))->method('PollActivityTaskQueue')
            ->willReturnOnConsecutiveCalls($task, new PollActivityTaskQueueResponse());
        $inner->expects(self::once())->method('RespondActivityTaskFailed')->willReturnCallback(static function (RespondActivityTaskFailedRequest $request) use (&$failed): RespondActivityTaskFailedResponse {
            $failed = $request;

            return new RespondActivityTaskFailedResponse();
        });
        $store = new InMemoryEventStore();
        $worker = new TemporalActivityWorker(
            new WorkflowServiceActivityRpc(new PayloadCodecWorkflowServiceClient($inner, new FailingCodec())),
            new TemporalConnection('localhost:7233', 'test-namespace'),
            new ActivityMessageProcessor($store, new NoopActivityTransport(), new RegistryActivityExecutor(), new NullWorkflowResumeDispatcher(), $this->createMock(ActivityHeartbeatSenderInterface::class)),
            $store,
            $this->createMock(ActivityHeartbeatSenderInterface::class),
        );

        // What the Messenger transport and the console commands do: one poll after the other.
        $worker->pollOnce();
        $worker->pollOnce();

        self::assertInstanceOf(RespondActivityTaskFailedRequest::class, $failed);
        self::assertSame('act-token', $failed->getTaskToken());
        self::assertStringContainsString('unknown key k2', (string) $failed->getFailure()?->getMessage());
        self::assertSame('', $failed->getFailure()?->getStackTrace(), 'a stack trace may quote key material or plaintext');
        self::assertSame(ActivityTaskFailedCause::ACTIVITY_TASK_FAILED_CAUSE_ACTIVITY_WORKER_UNHANDLED_FAILURE, $failed->getCause());
    }

    /**
     * The task closed or timed out before the answer: nothing is left to answer, and the poll ends empty.
     */
    public function testAStaleTaskOnTheFailedAnswerStillEndsInAnEmptyPoll(): void
    {
        $inner = $this->createMock(WorkflowServiceClientInterface::class);
        $inner->method('PollActivityTaskQueue')->willReturn(new PollActivityTaskQueueResponse(['task_token' => 'act-token', 'input' => self::undecodable()]));
        $inner->expects(self::once())->method('RespondActivityTaskFailed')->willThrowException(new \RuntimeException('Temporal gRPC error [5]: not found', 5));

        $poll = (new PayloadCodecWorkflowServiceClient($inner, new FailingCodec()))->PollActivityTaskQueue(new PollActivityTaskQueueRequest());

        self::assertSame('', $poll->getTaskToken());
    }

    public function testAnyOtherErrorOnTheFailedAnswerPropagates(): void
    {
        $inner = $this->createMock(WorkflowServiceClientInterface::class);
        $inner->method('PollActivityTaskQueue')->willReturn(new PollActivityTaskQueueResponse(['task_token' => 'act-token', 'input' => self::undecodable()]));
        $inner->method('RespondActivityTaskFailed')->willThrowException(new \RuntimeException('Temporal gRPC error [14]: unavailable', 14));

        $this->expectExceptionCode(14);
        (new PayloadCodecWorkflowServiceClient($inner, new FailingCodec()))->PollActivityTaskQueue(new PollActivityTaskQueueRequest());
    }

    /**
     * Outside a task poll there is no task to fail: the caller gets the decode error.
     */
    public function testADecodeFailureOutsideATaskPollReachesTheCaller(): void
    {
        $started = new WorkflowExecutionStartedEventAttributes(['input' => self::undecodable()]);
        $inner = $this->createMock(WorkflowServiceClientInterface::class);
        $inner->method('GetWorkflowExecutionHistory')->willReturn(new GetWorkflowExecutionHistoryResponse([
            'history' => new History(['events' => [new HistoryEvent(['workflow_execution_started_event_attributes' => $started])]]),
        ]));
        $inner->expects(self::never())->method('RespondWorkflowTaskFailed');

        $this->expectExceptionMessage('unknown key k2');
        (new PayloadCodecWorkflowServiceClient($inner, new FailingCodec()))->GetWorkflowExecutionHistory(new GetWorkflowExecutionHistoryRequest());
    }

    private static function undecodable(): Payloads
    {
        return new Payloads(['payloads' => [new Payload(['metadata' => ['encoding' => 'binary/encrypted'], 'data' => 'ciphertext'])]]);
    }
}

final class FailingCodec implements PayloadCodecInterface
{
    public function encode(Payload $payload): Payload
    {
        return $payload;
    }

    public function decode(Payload $payload): Payload
    {
        throw new \RuntimeException('unknown key k2');
    }
}
