<?php

declare(strict_types=1);

namespace unit\Gplanchat\Bridge\Temporal;

use Google\Protobuf\Timestamp;
use Gplanchat\Bridge\Temporal\Store\TaskQueueKind;
use Gplanchat\Bridge\Temporal\Store\TemporalTaskQueueProbe;
use Gplanchat\Bridge\Temporal\TemporalConnection;
use Gplanchat\Bridge\Temporal\WorkflowServiceClientInterface;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Enums\V1\TaskQueueType;
use Temporal\Api\Taskqueue\V1\PollerInfo;
use Temporal\Api\Workflowservice\V1\DescribeTaskQueueRequest;
use Temporal\Api\Workflowservice\V1\DescribeTaskQueueResponse;

final class TemporalTaskQueueProbeTest extends TestCase
{
    public function testEachDeclaredTypeIsAskedOnItsOwnQueue(): void
    {
        $asked = [];
        $client = $this->createMock(WorkflowServiceClientInterface::class);
        $client->method('DescribeTaskQueue')->willReturnCallback(static function (DescribeTaskQueueRequest $request) use (&$asked): DescribeTaskQueueResponse {
            $asked[] = [$request->getNamespace(), $request->getTaskQueue()?->getName(), $request->getTaskQueueType()];

            return new DescribeTaskQueueResponse();
        });

        (new TemporalTaskQueueProbe($client, $this->connection()))->describe([TaskQueueKind::Workflow, TaskQueueKind::Activity, TaskQueueKind::Nexus]);

        self::assertSame([
            ['durable-test', 'wf-q', TaskQueueType::TASK_QUEUE_TYPE_WORKFLOW],
            ['durable-test', 'act-q', TaskQueueType::TASK_QUEUE_TYPE_ACTIVITY],
            ['durable-test', 'nexus-q', TaskQueueType::TASK_QUEUE_TYPE_NEXUS],
        ], $asked);
    }

    public function testThePollersAreCountedAndTheLatestPollKept(): void
    {
        $response = new DescribeTaskQueueResponse();
        $response->setPollers([
            new PollerInfo(['last_access_time' => new Timestamp(['seconds' => 1_700_000_000])]),
            new PollerInfo(['last_access_time' => new Timestamp(['seconds' => 1_700_000_060])]),
        ]);

        [$workflow] = (new TemporalTaskQueueProbe($this->client($response), $this->connection()))->describe([TaskQueueKind::Workflow]);

        self::assertSame(TaskQueueKind::Workflow, $workflow->kind);
        self::assertSame('wf-q', $workflow->taskQueue);
        self::assertSame(2, $workflow->pollers);
        self::assertSame('1700000060', $workflow->lastPolledAt?->format('U'));
        self::assertNull($workflow->error);
        self::assertTrue($workflow->polledSince(new \DateTimeImmutable('@1700000030')));
        self::assertFalse($workflow->polledSince(new \DateTimeImmutable('@1700000090')));
    }

    /**
     * A server that refuses one type (Nexus before 1.25) must not hide what it says of the others,
     * and an unanswered probe never passes for a polled queue: a server that is down must not look healthy.
     */
    public function testAFailingQueueIsReportedAloneAndNeverReadAsPolled(): void
    {
        $client = $this->createMock(WorkflowServiceClientInterface::class);
        $client->method('DescribeTaskQueue')->willReturnCallback(static function (DescribeTaskQueueRequest $request): DescribeTaskQueueResponse {
            if (TaskQueueType::TASK_QUEUE_TYPE_NEXUS === $request->getTaskQueueType()) {
                throw new \RuntimeException('invalid TaskQueueType');
            }

            return new DescribeTaskQueueResponse();
        });

        [$workflow, $nexus] = (new TemporalTaskQueueProbe($client, $this->connection()))->describe([TaskQueueKind::Workflow, TaskQueueKind::Nexus]);

        self::assertNull($workflow->error);
        self::assertSame('invalid TaskQueueType', $nexus->error);
        self::assertFalse($nexus->polledSince(new \DateTimeImmutable('@0')));
    }

    private function connection(): TemporalConnection
    {
        return new TemporalConnection('localhost:7233', 'durable-test', workflowTaskQueue: 'wf-q', activityTaskQueue: 'act-q', nexusTaskQueue: 'nexus-q');
    }

    private function client(DescribeTaskQueueResponse $response): WorkflowServiceClientInterface
    {
        $client = $this->createMock(WorkflowServiceClientInterface::class);
        $client->method('DescribeTaskQueue')->willReturn($response);

        return $client;
    }
}
