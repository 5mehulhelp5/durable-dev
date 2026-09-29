<?php

declare(strict_types=1);

namespace unit\Gplanchat\Bridge\Temporal\Http;

use Gplanchat\Bridge\Temporal\Http\JsonGatewayRequest;
use Gplanchat\Bridge\Temporal\Http\JsonGatewayRoutes;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Enums\V1\TaskQueueType;
use Temporal\Api\Taskqueue\V1\TaskQueue;
use Temporal\Api\Workflowservice\V1\DescribeTaskQueueRequest;

final class JsonGatewayRequestTest extends TestCase
{
    public function testPlaceholdersAreProtoPathsReadFromTheCamelCaseJson(): void
    {
        $fields = ['namespace' => 'durable test', 'execution' => ['workflowId' => 'order/42', 'runId' => 'r1']];

        self::assertSame(
            '/api/v1/namespaces/durable%20test/workflows/order%2F42/history',
            JsonGatewayRequest::path('/api/v1/namespaces/{namespace}/workflows/{execution.workflow_id}/history', $fields),
        );
    }

    public function testAMissingPlaceholderFieldBecomesAnEmptySegment(): void
    {
        self::assertSame('/x//y', JsonGatewayRequest::path('/x/{query.query_type}/y', ['namespace' => 'n']));
    }

    public function testTheQueryFlattensNestedFieldsWithDotsAndRepeatsLists(): void
    {
        $fields = [
            'namespace' => 'n',
            'execution' => ['workflowId' => 'w', 'runId' => ''],
            'maximumPageSize' => 200,
            'skipArchival' => true,
            'nextPageToken' => 'AQ==',
            'ids' => ['a', 'b'],
        ];

        self::assertSame(
            'namespace=n&execution.workflowId=w&execution.runId=&maximumPageSize=200&skipArchival=true&nextPageToken=AQ%3D%3D&ids=a&ids=b',
            JsonGatewayRequest::query($fields),
        );
    }

    /**
     * The one GET route whose query carries an enum: the gateway takes it by name, as the JSON
     * form writes it.
     */
    public function testATaskQueueDescriptionNamesTheQueueInThePathAndItsTypeInTheQuery(): void
    {
        $request = new DescribeTaskQueueRequest();
        $request->setNamespace('default');
        $request->setTaskQueue(new TaskQueue(['name' => 'durable-activities']));
        $request->setTaskQueueType(TaskQueueType::TASK_QUEUE_TYPE_ACTIVITY);
        /** @var array<string, mixed> $fields */
        $fields = json_decode($request->serializeToJsonString(), true, 512, \JSON_THROW_ON_ERROR);
        [, $template] = JsonGatewayRoutes::ROUTES['DescribeTaskQueue'];

        self::assertSame('/api/v1/namespaces/default/task-queues/durable-activities', JsonGatewayRequest::path($template, $fields));
        self::assertStringContainsString('taskQueueType=TASK_QUEUE_TYPE_ACTIVITY', JsonGatewayRequest::query($fields));
    }
}
