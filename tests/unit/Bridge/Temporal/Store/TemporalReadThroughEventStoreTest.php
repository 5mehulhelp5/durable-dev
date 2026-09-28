<?php

declare(strict_types=1);

namespace unit\Gplanchat\Bridge\Temporal\Store;

use Gplanchat\Bridge\Temporal\Grpc\TemporalHistoryCursor;
use Gplanchat\Bridge\Temporal\Store\TemporalReadThroughEventStore;
use Gplanchat\Bridge\Temporal\WorkflowClientInterface;
use Gplanchat\Bridge\Temporal\WorkflowServiceClientInterface;
use Gplanchat\Durable\Event\ExecutionCompleted;
use Gplanchat\Durable\Event\ExecutionStarted;
use Gplanchat\Durable\Store\InMemoryEventStore;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\History;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryRequest;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryResponse;

/**
 * A child Durable starts on Temporal lives under its own execution id, not under the `durable-`
 * workflow id a top-level run gets. Reading only the latter, the store found no child, and the
 * history port answered "never started" for a child that had completed (#326).
 */
final class TemporalReadThroughEventStoreTest extends TestCase
{
    public function testAChildIsReadUnderItsOwnWorkflowId(): void
    {
        $store = $this->storeOver(['child-1' => [EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED, EventType::EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED]]);

        $classes = [];
        foreach ($store->readStream('child-1') as $event) {
            $classes[] = $event::class;
        }

        self::assertSame([ExecutionStarted::class, ExecutionCompleted::class], $classes);
        self::assertSame(2, $store->countEventsInStream('child-1'));
        self::assertCount(2, iterator_to_array($store->readStreamWithRecordedAt('child-1'), false));
    }

    public function testDurablesOwnWorkflowIdComesFirst(): void
    {
        $store = $this->storeOver([
            'durable-exec-1' => [EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED],
            'exec-1' => [EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED, EventType::EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED],
        ]);

        self::assertSame(1, $store->countEventsInStream('exec-1'));
    }

    public function testAnExecutionFoundUnderNeitherIdIsEmpty(): void
    {
        self::assertSame(0, $this->storeOver([])->countEventsInStream('nobody'));
    }

    /**
     * @param array<string, list<int>> $histories event types, by workflow id
     */
    private function storeOver(array $histories): TemporalReadThroughEventStore
    {
        $client = $this->createStub(WorkflowServiceClientInterface::class);
        $client->method('GetWorkflowExecutionHistory')->willReturnCallback(
            static function (GetWorkflowExecutionHistoryRequest $request) use ($histories): GetWorkflowExecutionHistoryResponse {
                $types = $histories[$request->getExecution()?->getWorkflowId() ?? ''] ?? throw new \RuntimeException('not found', 5);
                $events = [];
                foreach ($types as $index => $type) {
                    $events[] = new HistoryEvent(['event_id' => $index + 1, 'event_type' => $type]);
                }

                return new GetWorkflowExecutionHistoryResponse(['history' => new History(['events' => $events])]);
            },
        );

        $workflowClient = $this->createStub(WorkflowClientInterface::class);
        $workflowClient->method('workflowId')->willReturnCallback(static fn(string $executionId): string => 'durable-' . $executionId);

        return new TemporalReadThroughEventStore(new InMemoryEventStore(), new TemporalHistoryCursor($client, 'durable-test'), $workflowClient);
    }
}
