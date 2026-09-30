<?php

declare(strict_types=1);

namespace unit\DurableModule;

use Gplanchat\Bridge\Temporal\Codec\JsonPlainPayload;
use Gplanchat\Bridge\Temporal\Http\Psr18Http;
use Gplanchat\DurableModule\Runtime\RuntimeFactory;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\History;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\History\V1\WorkflowExecutionCompletedEventAttributes;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryResponse;
use unit\DurableModule\Fixture\OrderWorkflow;
use unit\DurableModule\Fixture\RecordingOrderActivities;

/**
 * `run()` follows the configured backend (#765): with a DSN, the workflow is started on the cluster
 * and `run()` waits for the result the cluster records, as `workflowClient()` would; without one,
 * it runs in this process.
 */
final class RunFollowsTheBackendTest extends TestCase
{
    public function testWithATemporalDsnRunStartsOnTheClusterAndReturnsItsResult(): void
    {
        $requests = [];
        $cluster = new class ($requests) implements ClientInterface {
            /** @param list<string> $requests */
            public function __construct(private array &$requests) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = $request->getMethod() . ' ' . $request->getUri()->getPath();
                if ('POST' === $request->getMethod()) {
                    return new Response(200, [], '{}');
                }

                $completed = new HistoryEvent([
                    'event_id' => 1,
                    'event_type' => EventType::EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED,
                    'workflow_execution_completed_event_attributes' => new WorkflowExecutionCompletedEventAttributes([
                        'result' => new Payloads(['payloads' => [JsonPlainPayload::encode('from the cluster')]]),
                    ]),
                ]);

                return new Response(200, [], (new GetWorkflowExecutionHistoryResponse([
                    'history' => new History(['events' => [$completed]]),
                ]))->serializeToJsonString());
            }
        };
        $http = new HttpFactory();

        $result = (new RuntimeFactory(
            workflowClasses: [OrderWorkflow::class],
            activityHandlers: [new RecordingOrderActivities()],
            temporalDsn: 'temporal+http://127.0.0.1:7243?namespace=default',
            jsonGateway: new Psr18Http($cluster, $http, $http),
        ))->create()->run(OrderWorkflow::class, ['orderId' => 'ORD-1'], 'order-1');

        self::assertSame('from the cluster', $result);
        self::assertContains('POST /api/v1/namespaces/default/workflows/durable-order-1', $requests);
    }

    public function testWithoutADsnRunExecutesInThisProcess(): void
    {
        $result = (new RuntimeFactory(
            workflowClasses: [OrderWorkflow::class],
            activityHandlers: [new RecordingOrderActivities()],
        ))->create()->run(OrderWorkflow::class, ['orderId' => 'ORD-1']);

        self::assertSame('notify:charge:ORD-1', $result);
    }
}
