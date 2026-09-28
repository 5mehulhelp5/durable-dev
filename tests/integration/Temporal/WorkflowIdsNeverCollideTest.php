<?php

declare(strict_types=1);

namespace integration\Temporal;

use Gplanchat\Bridge\Temporal\Codec\JsonPlainPayload;
use Gplanchat\Bridge\Temporal\Grpc\TemporalHistoryCursor;
use Gplanchat\Bridge\Temporal\Grpc\WorkflowServiceExecutionRpc;
use Gplanchat\Bridge\Temporal\Journal\JournalExecutionIdResolver;
use Gplanchat\Bridge\Temporal\Store\TemporalWorkflowRunCatalog;
use Gplanchat\Bridge\Temporal\TemporalConnection;
use Gplanchat\Bridge\Temporal\WorkflowClient;
use Gplanchat\Bridge\Temporal\WorkflowServiceClientFactory;
use Gplanchat\Bridge\Temporal\WorkflowServiceClientInterface;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Api\Taskqueue\V1\TaskQueue;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;

/**
 * #566 against a real server: `order/42` and `order 42` were one workflow id before, so the second
 * start was refused. Both start now and are addressed apart, a run started under the old id is still
 * reached for one release, and only by the execution that started it.
 */
final class WorkflowIdsNeverCollideTest extends TestCase
{
    use FreshNamespace;

    private TemporalConnection $connection;
    private WorkflowServiceClientInterface $grpc;
    private WorkflowClient $client;

    protected function setUp(): void
    {
        $this->connection = self::freshNamespaceConnection();
        $this->grpc = WorkflowServiceClientFactory::create($this->connection);
        $this->client = new WorkflowClient($this->grpc, $this->connection, new TemporalHistoryCursor($this->grpc, $this->connection), new WorkflowServiceExecutionRpc($this->grpc));
    }

    public function testTwoIdsTheOldMappingMergedBothStartAndStayApart(): void
    {
        $first = $this->client->startAsync('App\\OrderWorkflow', [], 'order/42');
        $second = $this->client->startAsync('App\\OrderWorkflow', [], 'order 42');

        self::assertNotSame($first, $second);
        $catalog = new TemporalWorkflowRunCatalog($this->grpc, $this->connection);
        self::assertSame('order/42', $catalog->findRun('order/42')?->executionId);
        self::assertSame('order 42', $catalog->findRun('order 42')?->executionId);
        self::assertSame($first, $this->client->workflowId('order/42'));
        self::assertSame($second, $this->client->workflowId('order 42'));
    }

    public function testARunStartedUnderTheLegacyIdIsReachedOnlyByItsOwnExecution(): void
    {
        // As the client started it before #566: the lossy workflow id, the execution id in the memo.
        $memo = new Memo();
        $memo->getFields()[JournalExecutionIdResolver::MEMO_KEY_DURABLE_EXECUTION_ID] = JsonPlainPayload::encode('order/7');
        $this->grpc->StartWorkflowExecution(new StartWorkflowExecutionRequest([
            'namespace' => $this->connection->namespace->name(),
            'workflow_id' => 'durable-order-7',
            'workflow_type' => new WorkflowType(['name' => 'App\\OrderWorkflow']),
            'task_queue' => new TaskQueue(['name' => 'nobody-polls']),
            'request_id' => bin2hex(random_bytes(16)),
            'memo' => $memo,
        ]));

        $catalog = new TemporalWorkflowRunCatalog($this->grpc, $this->connection);

        self::assertSame('durable-order-7', $this->client->workflowId('order/7'));
        self::assertSame('order/7', $catalog->findRun('order/7')?->executionId);
        self::assertSame(WorkflowClient::workflowIdOf('order 7'), $this->client->workflowId('order 7'), 'another execution never takes it');
        self::assertNull($catalog->findRun('order 7'));
    }
}
