<?php

declare(strict_types=1);

/**
 * Serves the demo Nexus contracts on a Temporal server, for the bench jobs (#663).
 *
 * Usage: php nexus-demo-harness.php <address> <namespace> [--serve=stock,billing,delivery]
 *                                   [--prefix=] [--transport=auto]
 *
 * Creates the endpoints the benches call (`demo-shop-stock`, `demo-business-billing`,
 * `demo-laravel-delivery`, each behind the prefix), prints "ready", and serves until SIGTERM, which
 * deletes them. Exits 1 if an endpoint cannot be created — one that already exists included, since
 * something else then serves it — or if one of its two workers dies. `--serve` leaves out what the
 * bench under test serves itself.
 *
 * Runs from the repository root's vendor/ (`composer install` there), since the fixture it serves
 * lives in the root's autoload-dev. Needs ext-pcntl, for SIGTERM to reach the endpoint cleanup.
 *
 * Two processes, as in worker.php: the Nexus worker and the workflow worker both long-poll, and one
 * would starve the other. The workflow worker runs the fulfilling workflows and the caller.
 */

use Gplanchat\Bridge\Temporal\Grpc\TemporalHistoryCursor;
use Gplanchat\Bridge\Temporal\Grpc\WorkflowServiceNexusRpc;
use Gplanchat\Bridge\Temporal\TemporalConnection;
use Gplanchat\Bridge\Temporal\Worker\TemporalNexusWorker;
use Gplanchat\Bridge\Temporal\Worker\WorkflowTaskProcessor;
use Gplanchat\Bridge\Temporal\Worker\WorkflowTaskRunner;
use Gplanchat\Bridge\Temporal\WorkflowServiceClientFactory;
use Gplanchat\Durable\WorkflowRegistry;
use integration\Temporal\Fixtures\DemoHarness;
use Temporal\Api\Nexus\V1\EndpointSpec;
use Temporal\Api\Nexus\V1\EndpointTarget;
use Temporal\Api\Nexus\V1\EndpointTarget\Worker;
use Temporal\Api\Operatorservice\V1\CreateNexusEndpointRequest;
use Temporal\Api\Operatorservice\V1\CreateNexusEndpointResponse;
use Temporal\Api\Operatorservice\V1\DeleteNexusEndpointRequest;
use Temporal\Api\Operatorservice\V1\DeleteNexusEndpointResponse;

require __DIR__ . '/../../../vendor/autoload.php';

// `?? []`: the CLI always defines $argv, and PHPStan on PHP 8.5 doubts it.
$arguments = array_slice($argv ?? [], 1);
$options = [];
$positional = [];
foreach ($arguments as $argument) {
    if (1 === preg_match('/^--([a-z]+)=(.*)$/', $argument, $match)) {
        $options[$match[1]] = $match[2];
    } else {
        $positional[] = $argument;
    }
}
[$address, $namespace] = $positional + ['', ''];
if ('' === $address || '' === $namespace) {
    fwrite(STDERR, "usage: php nexus-demo-harness.php <address> <namespace> [--serve=stock,billing,delivery] [--prefix=] [--transport=auto]\n");
    exit(2);
}
$prefix = $options['prefix'] ?? '';
$services = explode(',', $options['serve'] ?? implode(',', array_keys(DemoHarness::ENDPOINTS)));
if ([] !== ($unknown = array_diff($services, array_keys(DemoHarness::ENDPOINTS)))) {
    fwrite(STDERR, 'unknown service(s) in --serve: ' . implode(', ', $unknown) . "\n");
    exit(2);
}

$connection = new TemporalConnection(
    target: $address,
    namespace: $namespace,
    identity: 'durable-demo-harness',
    workflowTaskQueue: $prefix . 'demo-harness',
    nexusTaskQueue: $prefix . 'demo-harness-nexus',
    transport: $options['transport'] ?? TemporalConnection::TRANSPORT_AUTO,
);
$client = WorkflowServiceClientFactory::create($connection);

if (isset($options['role'])) {
    $worker = 'nexus' === $options['role']
        ? new TemporalNexusWorker(new WorkflowServiceNexusRpc($client), $connection, DemoHarness::registry($services))
        : new WorkflowTaskProcessor($client, $connection, new WorkflowTaskRunner(new TemporalHistoryCursor($client, $connection), (static function (): WorkflowRegistry {
            DemoHarness::registerWorkflows($registry = new WorkflowRegistry());

            return $registry;
        })(), $connection));

    // ponytail: a worker outlives a parent killed without SIGTERM; it checks /proc (Linux, as CI).
    while (file_exists('/proc/' . $options['parent'])) {
        try {
            $worker instanceof TemporalNexusWorker ? $worker->pollOnce() : $worker->processOne();
        } catch (Throwable $e) {
            fwrite(STDERR, "demo harness {$options['role']} worker: " . $e::class . ': ' . $e->getMessage() . "\n");
            sleep(1);
        }
    }
    exit(0);
}

$operator = '/temporal.api.operatorservice.v1.OperatorService/';
$transport = WorkflowServiceClientFactory::createTransport($connection);
/** @var list<array{string, int}> $created endpoint id and version */
$created = [];
$deleteEndpoints = static function () use ($transport, $operator, &$created): void {
    foreach ($created as [$id, $version]) {
        try {
            $transport->unary($operator . 'DeleteNexusEndpoint', new DeleteNexusEndpointRequest(['id' => $id, 'version' => $version]), DeleteNexusEndpointResponse::class, [], 10_000);
        } catch (RuntimeException) {
        }
    }
};
/** @var array<string, resource> $workers */
$workers = [];
/** @param array<string, resource> $running */
$stop = static function (int $code, array $running) use ($deleteEndpoints): never {
    foreach ($running as $worker) {
        proc_terminate($worker);
    }
    $deleteEndpoints();
    exit($code);
};
if (!function_exists('pcntl_async_signals')) {
    fwrite(STDERR, "ext-pcntl is required: without it, SIGTERM would leave the endpoints behind\n");
    exit(2);
}
pcntl_async_signals(true);
$onSignal = static function () use (&$workers, $stop): never {
    $stop(0, $workers);
};
pcntl_signal(SIGTERM, $onSignal);
pcntl_signal(SIGINT, $onSignal);

foreach ($services as $service) {
    $name = $prefix . DemoHarness::ENDPOINTS[$service];
    $spec = new EndpointSpec(['name' => $name, 'target' => new EndpointTarget(['worker' => new Worker([
        'namespace' => $namespace,
        'task_queue' => $connection->nexusTaskQueue->name(),
    ])])]);
    $deadline = microtime(true) + 30.0;
    while (true) {
        try {
            $endpoint = $transport->unary($operator . 'CreateNexusEndpoint', new CreateNexusEndpointRequest(['spec' => $spec]), CreateNexusEndpointResponse::class, [], 10_000)->getEndpoint();
            $created[] = [(string) $endpoint?->getId(), (int) $endpoint?->getVersion()];
            echo "endpoint {$name} -> {$namespace} / {$connection->nexusTaskQueue->name()}\n";
            break;
        } catch (RuntimeException $e) {
            // 6, already exists: someone else serves this name, and waiting will not change that.
            // Anything else may be a namespace that has not propagated yet.
            if (6 === $e->getCode() || microtime(true) > $deadline) {
                fwrite(STDERR, "cannot create the endpoint {$name}: {$e->getMessage()}\n");
                $stop(1, $workers);
            }
            usleep(500_000);
        }
    }
}

foreach (['nexus', 'workflow'] as $role) {
    $worker = proc_open([PHP_BINARY, __FILE__, ...$arguments, '--role=' . $role, '--parent=' . (int) getmypid()], [1 => STDOUT, 2 => STDERR], $pipes);
    if (false === $worker) {
        fwrite(STDERR, "cannot start the {$role} worker\n");
        $stop(1, $workers);
    }
    $workers[$role] = $worker;
}
echo "ready\n";
while (true) {
    foreach ($workers as $role => $worker) {
        if (!proc_get_status($worker)['running']) {
            fwrite(STDERR, "the {$role} worker stopped\n");
            $stop(1, $workers);
        }
    }
    sleep(1);
}
