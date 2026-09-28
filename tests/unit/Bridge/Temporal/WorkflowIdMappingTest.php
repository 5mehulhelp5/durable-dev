<?php

declare(strict_types=1);

namespace unit\Gplanchat\Bridge\Temporal;

use Gplanchat\Bridge\Temporal\Codec\JsonPlainPayload;
use Gplanchat\Bridge\Temporal\Grpc\TemporalHistoryCursor;
use Gplanchat\Bridge\Temporal\Grpc\WorkflowServiceExecutionRpc;
use Gplanchat\Bridge\Temporal\Journal\JournalExecutionIdResolver;
use Gplanchat\Bridge\Temporal\TemporalConnection;
use Gplanchat\Bridge\Temporal\WorkflowClient;
use Gplanchat\Bridge\Temporal\WorkflowServiceClientInterface;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Workflow\V1\WorkflowExecutionInfo;
use Temporal\Api\Workflowservice\V1\DescribeWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\DescribeWorkflowExecutionResponse;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionResponse;

/**
 * #566: two execution ids must never share a workflow id. The old mapping replaced every character
 * outside `[a-zA-Z0-9._-]` with `-` and cut at 900, so `order/42`, `order 42` and `order-42` all
 * addressed one run.
 */
final class WorkflowIdMappingTest extends TestCase
{
    public function testAnIdTheSanitisationLeavesAloneKeepsItsWorkflowId(): void
    {
        self::assertSame('durable-exec-1', WorkflowClient::workflowIdOf('exec-1'));
        self::assertSame('durable-01J9ZK3Q.order_2', WorkflowClient::workflowIdOf('01J9ZK3Q.order_2'));
        self::assertSame('durable-' . str_repeat('a', 900), WorkflowClient::workflowIdOf(str_repeat('a', 900)));
    }

    public function testIdsTheOldMappingMergedNowStayApart(): void
    {
        $ids = ['order/42', 'order 42', 'order-42', str_repeat('a', 900) . 'x', str_repeat('a', 900) . 'y'];

        $workflowIds = array_map(WorkflowClient::workflowIdOf(...), $ids);

        self::assertCount(\count($ids), array_unique($workflowIds));
    }

    public function testAChangedIdIsHashedAndCanNeverSpellAnUnchangedOne(): void
    {
        $hashed = WorkflowClient::workflowIdOf('order/42');

        self::assertStringStartsWith('durable-order-42~', $hashed);
        self::assertStringNotContainsString('~', WorkflowClient::workflowIdOf('order-42'));
        self::assertLessThanOrEqual(908, \strlen(WorkflowClient::workflowIdOf(str_repeat('/', 2000))), 'no longer than the old mapping allowed');
    }

    public function testOnlyAChangedIdHasALegacyWorkflowId(): void
    {
        self::assertNull(WorkflowClient::legacyWorkflowIdOf('exec-1'));
        self::assertSame('durable-order-42', WorkflowClient::legacyWorkflowIdOf('order/42'));
    }

    public function testAStartUsesTheNewWorkflowIdAndLooksNothingUp(): void
    {
        $started = null;
        $grpc = $this->createMock(WorkflowServiceClientInterface::class);
        $grpc->expects($this->never())->method('DescribeWorkflowExecution');
        $grpc->method('StartWorkflowExecution')->willReturnCallback(static function (StartWorkflowExecutionRequest $request) use (&$started): StartWorkflowExecutionResponse {
            $started = $request->getWorkflowId();

            return new StartWorkflowExecutionResponse();
        });

        $this->client($grpc)->startAsync('App\\OrderWorkflow', [], 'order/42');

        self::assertSame(WorkflowClient::workflowIdOf('order/42'), $started);
    }

    public function testAnUnchangedIdIsAddressedWithoutALookup(): void
    {
        $grpc = $this->createMock(WorkflowServiceClientInterface::class);
        $grpc->expects($this->never())->method('DescribeWorkflowExecution');

        self::assertSame('durable-exec-1', $this->client($grpc)->workflowId('exec-1'));
    }

    public function testARunStartedUnderTheNewIdIsAddressedThere(): void
    {
        $client = $this->client($this->server([WorkflowClient::workflowIdOf('order/42') => 'order/42']));

        self::assertSame(WorkflowClient::workflowIdOf('order/42'), $client->workflowId('order/42'));
    }

    public function testARunStartedBeforeTheUpgradeIsStillReached(): void
    {
        $client = $this->client($this->server(['durable-order-42' => 'order/42']));

        self::assertSame('durable-order-42', $client->workflowId('order/42'));
    }

    public function testTheLegacyIdOfAnotherExecutionIsNotTaken(): void
    {
        // `order 42` started before the upgrade holds `durable-order-42`: `order/42` must not reach it.
        $client = $this->client($this->server(['durable-order-42' => 'order 42']));

        self::assertSame(WorkflowClient::workflowIdOf('order/42'), $client->workflowId('order/42'));
    }

    /**
     * A server holding these runs, by workflow id, each started with this execution id in its memo.
     *
     * @param array<string, string> $runs
     */
    private function server(array $runs): WorkflowServiceClientInterface
    {
        $grpc = $this->createMock(WorkflowServiceClientInterface::class);
        $grpc->method('DescribeWorkflowExecution')->willReturnCallback(static function (DescribeWorkflowExecutionRequest $request) use ($runs): DescribeWorkflowExecutionResponse {
            $workflowId = (string) $request->getExecution()?->getWorkflowId();
            if (!isset($runs[$workflowId])) {
                throw new \RuntimeException('workflow not found', 5);
            }
            $memo = new Memo();
            $memo->getFields()[JournalExecutionIdResolver::MEMO_KEY_DURABLE_EXECUTION_ID] = JsonPlainPayload::encode($runs[$workflowId]);

            return new DescribeWorkflowExecutionResponse(['workflow_execution_info' => new WorkflowExecutionInfo(['memo' => $memo])]);
        });

        return $grpc;
    }

    private function client(WorkflowServiceClientInterface $grpc): WorkflowClient
    {
        $connection = TemporalConnection::fromDsn('temporal://127.0.0.1:7233?namespace=default&tls=0');

        return new WorkflowClient($grpc, $connection, new TemporalHistoryCursor($grpc, $connection), new WorkflowServiceExecutionRpc($grpc));
    }
}
