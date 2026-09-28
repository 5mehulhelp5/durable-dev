<?php

declare(strict_types=1);

namespace integration\Temporal;

use Google\Protobuf\Duration;
use Gplanchat\Bridge\Temporal\DurableSearchAttributes;
use Gplanchat\Bridge\Temporal\TemporalConnection;
use Gplanchat\Bridge\Temporal\Worker\TemporalPolicyMapper;
use Gplanchat\Bridge\Temporal\WorkflowServiceClientFactory;
use Gplanchat\Durable\SearchAttributes;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Api\Enums\V1\IndexedValueType;
use Temporal\Api\Operatorservice\V1\AddSearchAttributesRequest;
use Temporal\Api\Operatorservice\V1\AddSearchAttributesResponse;
use Temporal\Api\Operatorservice\V1\ListSearchAttributesRequest;
use Temporal\Api\Operatorservice\V1\ListSearchAttributesResponse;
use Temporal\Api\Taskqueue\V1\TaskQueue;
use Temporal\Api\Workflowservice\V1\DeleteWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\DeleteWorkflowExecutionResponse;
use Temporal\Api\Workflowservice\V1\DescribeNamespaceRequest;
use Temporal\Api\Workflowservice\V1\DescribeNamespaceResponse;
use Temporal\Api\Workflowservice\V1\ListWorkflowExecutionsRequest;
use Temporal\Api\Workflowservice\V1\ListWorkflowExecutionsResponse;
use Temporal\Api\Workflowservice\V1\RegisterNamespaceRequest;
use Temporal\Api\Workflowservice\V1\RegisterNamespaceResponse;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionResponse;
use Temporal\Api\Workflowservice\V1\TerminateWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\TerminateWorkflowExecutionResponse;

/**
 * A namespace of its own for each test: the conformance suites assert exact sets ("an empty catalog
 * lists nothing", "the stream of exec-order") and reuse their execution ids from one run to the
 * next, which a namespace shared with the rest of the integration suite cannot honour.
 *
 * The client interface has no namespace RPC — the bridge never manages namespaces — so this goes
 * through the transport directly.
 */
trait FreshNamespace
{
    private const NAMESPACE_READY_TIMEOUT_SECONDS = 30.0;

    private static function freshNamespaceConnection(bool $searchAttributes = true): TemporalConnection
    {
        $address = getenv('DURABLE_TEMPORAL_ADDRESS');
        if (false === $address || '' === $address) {
            self::markTestSkipped('DURABLE_TEMPORAL_ADDRESS not set: no Temporal server.');
        }
        $transport = TemporalServerTestCase::transportFromEnv();
        if (TemporalConnection::TRANSPORT_GRPC === $transport && !\extension_loaded('grpc')) {
            self::markTestSkipped('ext-grpc is not loaded; set DURABLE_TEMPORAL_TRANSPORT=grpc-curl to run without it.');
        }

        // ponytail: the namespaces are never deleted; a dev server is thrown away with them.
        $namespace = 'durable-conformance-' . bin2hex(random_bytes(6));
        $queue = 'durable-conformance-' . bin2hex(random_bytes(6));
        $connection = new TemporalConnection(
            target: $address,
            namespace: $namespace,
            journalTaskQueue: $queue,
            identity: 'durable-conformance',
            workflowTaskQueue: $queue,
            activityTaskQueue: $queue,
            transport: $transport,
            // The conformance namespaces register them below, so the suite runs as an enabled host
            // unless it asks otherwise.
            searchAttributes: $searchAttributes,
        );

        $transportClient = WorkflowServiceClientFactory::createTransport($connection);
        $service = '/temporal.api.workflowservice.v1.WorkflowService/';
        $transportClient->unary($service . 'RegisterNamespace', new RegisterNamespaceRequest([
            'namespace' => $namespace,
            'workflow_execution_retention_period' => new Duration(['seconds' => 86_400]),
        ]), RegisterNamespaceResponse::class, [], 10_000);

        // A registration propagates asynchronously on a server with a namespace cache.
        self::awaitNamespace($namespace, 'still unknown', static fn() => $transportClient->unary($service . 'DescribeNamespace', new DescribeNamespaceRequest(['namespace' => $namespace]), DescribeNamespaceResponse::class, [], 5_000));

        // Durable writes these on every start, and a server refuses a start that names an
        // attribute the namespace has no mapping for (#558).
        // Through the same wait: on an older server (1.20) the operator service reads the namespace
        // from a cache that learns of it seconds after DescribeNamespace does. And 1.20 keeps these
        // attributes cluster-wide, so "already exists" (6) is the state wanted, not a failure (#523).
        $wanted = [
            DurableSearchAttributes::WORKFLOW_NAME => IndexedValueType::INDEXED_VALUE_TYPE_KEYWORD,
            DurableSearchAttributes::EXECUTION_ID => IndexedValueType::INDEXED_VALUE_TYPE_KEYWORD,
        ];
        $operator = '/temporal.api.operatorservice.v1.OperatorService/';
        self::awaitNamespace($namespace, 'still unknown to the operator service', static fn() => self::alreadyExistsIsFine(
            static fn() => $transportClient->unary($operator . 'AddSearchAttributes', new AddSearchAttributesRequest([
                'namespace' => $namespace,
                'search_attributes' => $wanted,
            ]), AddSearchAttributesResponse::class, [], 10_000),
            static fn(): ListSearchAttributesResponse => $transportClient->unary($operator . 'ListSearchAttributes', new ListSearchAttributesRequest(['namespace' => $namespace]), ListSearchAttributesResponse::class, [], 10_000),
            $wanted,
        ));
        // Listed at once, usable a couple of seconds later: until then a query naming the attribute
        // fails as a start would, without starting anything.
        self::awaitNamespace($namespace, 'has no usable Durable search attributes', static fn() => $transportClient->unary($service . 'ListWorkflowExecutions', new ListWorkflowExecutionsRequest([
            'namespace' => $namespace,
            'page_size' => 1,
            'query' => DurableSearchAttributes::EXECUTION_ID . " = 'probe' AND " . DurableSearchAttributes::WORKFLOW_NAME . " = 'probe'",
        ]), ListWorkflowExecutionsResponse::class, [], 5_000));

        if (!$searchAttributes) {
            return $connection;
        }

        // A query that names them can pass while a start that sets them is still refused: on 1.20
        // the start is checked against a mapping another component caches (#650). So the start
        // itself is the probe, on a queue no worker polls, and it leaves nothing behind: the
        // conformance suites assert exact sets on this namespace.
        $probeId = 'durable-namespace-probe';
        $probe = new StartWorkflowExecutionRequest([
            'namespace' => $namespace,
            'workflow_id' => $probeId,
            'workflow_type' => new WorkflowType(['name' => $probeId]),
            'task_queue' => new TaskQueue(['name' => $probeId]),
            'identity' => 'durable-conformance',
        ]);
        TemporalPolicyMapper::applySearchAttributes(DurableSearchAttributes::of($connection, $probeId, $probeId, SearchAttributes::none()), $probe);
        self::awaitNamespace($namespace, \sprintf('refuses a start that sets %s and %s', DurableSearchAttributes::WORKFLOW_NAME, DurableSearchAttributes::EXECUTION_ID), static function () use ($transportClient, $service, $probe): void {
            try {
                $probe->setRequestId(bin2hex(random_bytes(16)));
                $transportClient->unary($service . 'StartWorkflowExecution', $probe, StartWorkflowExecutionResponse::class, [], 5_000);
            } catch (\RuntimeException $failure) {
                // An earlier attempt started it and only its answer was lost.
                if (6 !== $failure->getCode()) {
                    throw $failure;
                }
            }
        });
        $execution = new WorkflowExecution(['workflow_id' => $probeId]);
        $transportClient->unary($service . 'TerminateWorkflowExecution', new TerminateWorkflowExecutionRequest([
            'namespace' => $namespace,
            'workflow_execution' => $execution,
        ]), TerminateWorkflowExecutionResponse::class, [], 5_000);
        // Deleted once its closed row is visible, or the late row would outlive the deletion.
        $listed = static fn(string $query): int => \count($transportClient->unary($service . 'ListWorkflowExecutions', new ListWorkflowExecutionsRequest([
            'namespace' => $namespace,
            'page_size' => 1,
            'query' => $query,
        ]), ListWorkflowExecutionsResponse::class, [], 5_000)->getExecutions());
        self::awaitNamespace($namespace, 'never lists its terminated probe', static fn() => 1 === $listed("WorkflowId = '{$probeId}' AND ExecutionStatus = 'Terminated'") ?: throw new \RuntimeException('not listed yet'));
        $transportClient->unary($service . 'DeleteWorkflowExecution', new DeleteWorkflowExecutionRequest([
            'namespace' => $namespace,
            'workflow_execution' => $execution,
        ]), DeleteWorkflowExecutionResponse::class, [], 5_000);
        self::awaitNamespace($namespace, 'still lists its deleted probe', static fn() => 0 === $listed('') ?: throw new \RuntimeException('still listed'));

        return $connection;
    }

    /**
     * "Already exists" is the state wanted only if it exists with the wanted type: an attribute
     * registered with another one would fail every query that names it, far from its cause.
     * A LogicException, so awaitNamespace() does not retry it until its timeout.
     *
     * @param callable(): mixed                         $add
     * @param callable(): ListSearchAttributesResponse $list
     * @param array<string, int>                        $wanted name => IndexedValueType
     */
    private static function alreadyExistsIsFine(callable $add, callable $list, array $wanted): void
    {
        try {
            $add();
        } catch (\RuntimeException $failure) {
            if (6 !== $failure->getCode()) {
                throw $failure;
            }
            $registered = [];
            foreach ($list()->getCustomAttributes() as $name => $type) {
                $registered[(string) $name] = (int) $type;
            }
            foreach ($wanted as $name => $type) {
                $actual = $registered[$name] ?? null;
                if ($type !== $actual) {
                    throw new \LogicException(\sprintf('Search attribute %s exists as %s, expected %s.', $name, null === $actual ? 'nothing' : IndexedValueType::name($actual), IndexedValueType::name($type)));
                }
            }
        }
    }

    private static function awaitNamespace(string $namespace, string $state, callable $probe): void
    {
        $deadline = microtime(true) + self::NAMESPACE_READY_TIMEOUT_SECONDS;
        while (true) {
            try {
                $probe();

                return;
            } catch (\RuntimeException $notYet) {
                if (microtime(true) > $deadline) {
                    self::fail(\sprintf('Namespace "%s" %s %.0f s after its registration: %s', $namespace, $state, self::NAMESPACE_READY_TIMEOUT_SECONDS, $notYet->getMessage()));
                }
                usleep(200_000);
            }
        }
    }
}
