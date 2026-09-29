<?php

declare(strict_types=1);

namespace integration\Temporal;

use Gplanchat\Bridge\Temporal\Grpc\TemporalHistoryCursor;
use Gplanchat\Bridge\Temporal\Grpc\WorkflowServiceExecutionRpc;
use Gplanchat\Bridge\Temporal\TemporalConnection;
use Gplanchat\Bridge\Temporal\WorkflowClient;
use Gplanchat\Bridge\Temporal\WorkflowServiceClientFactory;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Enums\V1\EventType;

/**
 * The harness the bench jobs call (#663): it serves the demo contracts, so a bench that calls
 * `stock`, `billing` or `delivery` has someone on the other side without the other three benches.
 *
 * What this pins is the harness as a bench sees it: one command, its endpoints, and the contract's
 * answers — three immediate, two through a workflow — reached by a caller that uses `nexusStub()`,
 * the way the benches' own workflows do.
 */
final class NexusDemoHarnessTest extends TestCase
{
    private string $address;
    private string $namespace;
    private string $prefix;
    /** @var list<array{process: resource, pipes: array<int, resource>}> */
    private array $harnesses = [];

    protected function setUp(): void
    {
        $address = getenv('DURABLE_TEMPORAL_ADDRESS');
        if (false === $address || '' === $address) {
            self::markTestSkipped('DURABLE_TEMPORAL_ADDRESS not set: no Temporal server.');
        }
        $this->address = $address;
        $this->namespace = getenv('DURABLE_TEMPORAL_NAMESPACE') ?: 'durable-test';
        // Endpoint names are unique for the whole cluster: a prefix keeps this test off the demo's.
        $this->prefix = 'it' . bin2hex(random_bytes(3)) . '-';
    }

    protected function tearDown(): void
    {
        foreach ($this->harnesses as $harness) {
            proc_terminate($harness['process'], \SIGTERM);
            proc_close($harness['process']);
        }
    }

    public function testACallerGetsEachContractsAnswerThroughTheHarness(): void
    {
        $harness = $this->startHarness();
        $this->awaitReady($harness);

        $connection = new TemporalConnection(
            target: $this->address,
            namespace: $this->namespace,
            identity: 'durable-demo-harness-test',
            workflowTaskQueue: $this->prefix . 'demo-harness',
            transport: TemporalServerTestCase::transportFromEnv(),
        );
        $client = WorkflowServiceClientFactory::create($connection);
        $workflows = new WorkflowClient($client, $connection, new TemporalHistoryCursor($client, $connection), new WorkflowServiceExecutionRpc($client));

        $executionId = 'demo-harness-caller-' . bin2hex(random_bytes(4));
        $workflows->startAsync('DemoHarnessCaller', ['prefix' => $this->prefix], $executionId);
        $answers = $workflows->pollForCompletion($executionId, 250, 240);

        self::assertSame([
            'verify' => ['accepted' => true, 'reason' => null],
            'reserve' => ['reserved' => true, 'missing' => []],
            'schedule' => ['scheduled' => true, 'slot' => 'next-day 09:00-12:00', 'carrier' => 'courier', 'reason' => null],
            'charge' => ['receipt' => 'RCP-ORD-1', 'charged' => 1200],
            'ship' => ['shipped' => true, 'tracking' => 'TRK-ORD-1'],
        ], $answers);

        // An operation answered through a workflow is the only kind the server records as started.
        $started = 0;
        foreach ((new TemporalHistoryCursor($client, $connection))->events(new WorkflowExecution(['workflow_id' => WorkflowClient::workflowIdOf($executionId)])) as $event) {
            if (EventType::EVENT_TYPE_NEXUS_OPERATION_STARTED === $event->getEventType()) {
                ++$started;
            }
        }
        self::assertSame(2, $started, 'charge and ship must answer through a workflow, and only they');
    }

    public function testASecondHarnessOnTheSameEndpointsExitsNonZero(): void
    {
        $this->awaitReady($this->startHarness());

        $second = $this->startHarness();
        $deadline = microtime(true) + 30.0;
        while (proc_get_status($second['process'])['running'] && microtime(true) < $deadline) {
            usleep(200_000);
        }
        $status = proc_get_status($second['process']);

        self::assertFalse($status['running'], 'the second harness kept running on endpoints it could not create');
        self::assertNotSame(0, $status['exitcode']);
    }

    public function testTheCallModePrintsOneOperationsAnswer(): void
    {
        $this->awaitReady($this->startHarness());

        $call = proc_open(
            [\PHP_BINARY, __DIR__ . '/nexus-demo-harness.php', $this->address, $this->namespace, '--prefix=' . $this->prefix, '--transport=' . TemporalServerTestCase::transportFromEnv(),
                '--call=billing/verify', '--input={"order":"ORD-9","amount":5,"currency":"EUR"}'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($call);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        self::assertSame(0, proc_close($call), $stderr);
        self::assertSame(['accepted' => true, 'reason' => null], json_decode($stdout, true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testTheBenchModeRoutesAServiceToTheBenchInsteadOfServingIt(): void
    {
        $harness = $this->startHarness('--bench=billing@' . $this->prefix . 'bench-nexus');
        $output = $this->awaitReady($harness);

        self::assertStringContainsString("endpoint {$this->prefix}demo-business-billing -> {$this->namespace} / {$this->prefix}bench-nexus", $output);
        self::assertStringContainsString("endpoint {$this->prefix}demo-shop-stock -> {$this->namespace} / {$this->prefix}demo-harness-nexus", $output);
    }

    /**
     * @return array{process: resource, pipes: array<int, resource>}
     */
    private function startHarness(string ...$options): array
    {
        $process = proc_open(
            [\PHP_BINARY, __DIR__ . '/nexus-demo-harness.php', $this->address, $this->namespace, '--prefix=' . $this->prefix, '--transport=' . TemporalServerTestCase::transportFromEnv(), ...$options],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process, 'the harness did not start');

        return $this->harnesses[] = ['process' => $process, 'pipes' => $pipes];
    }

    /**
     * @param array{process: resource, pipes: array<int, resource>} $harness
     */
    private function awaitReady(array $harness): string
    {
        $output = '';
        $deadline = microtime(true) + 60.0;
        stream_set_blocking($harness['pipes'][1], false);
        while (microtime(true) < $deadline) {
            $output .= (string) stream_get_contents($harness['pipes'][1]);
            if (str_contains($output, 'ready')) {
                return $output;
            }
            if (!proc_get_status($harness['process'])['running']) {
                break;
            }
            usleep(100_000);
        }

        self::fail("The harness never said ready.\nstdout: {$output}\nstderr: " . stream_get_contents($harness['pipes'][2]));
    }
}
