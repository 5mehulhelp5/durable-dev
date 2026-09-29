<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Gplanchat\Bridge\Temporal\TemporalConnection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The shop serves `stock` and calls `billing`, against a server, through its own workers (#665).
 *
 * As in the demo, `demo` serves `stock/reserve` with StockHandler (a tag under `when@demo`) on its
 * DBAL journal, and `demo_caller` runs OrderWorkflow on the cluster's. The root's demo harness (#663)
 * serves `billing`, routes `stock` to the shop's Nexus queue, and calls it. Each answer asserted here
 * is one only the shop gives. Needs a server with Nexus on, the root's vendor/, DURABLE_TEMPORAL_DSN
 * with the shop's `nexus_task_queue`, and a DATABASE_URL both profiles share.
 */
#[Group('nexus-bench')]
final class NexusBenchTest extends TestCase
{
    private const ON_HAND = 5;

    /** @var list<array{name: string, process: resource, log: string}> */
    private static array $processes = [];
    private static TemporalConnection $connection;
    private static string $dsn;
    private static string $database;
    private static string $variant;

    public static function setUpBeforeClass(): void
    {
        self::$dsn = (string) (getenv('DURABLE_TEMPORAL_DSN') ?: '');
        self::$database = (string) (getenv('DATABASE_URL') ?: '');
        if ('' === self::$dsn || '' === self::$database) {
            self::markTestSkipped('Set DURABLE_TEMPORAL_DSN (a server with Nexus on) and DATABASE_URL.');
        }
        self::$connection = TemporalConnection::fromDsn(self::$dsn);
        self::$variant = 'NEXUS_' . strtoupper(bin2hex(random_bytes(3)));

        try {
            self::startTheShopAndTheHarness();
        } catch (\Throwable $e) {
            // PHPUnit skips tearDownAfterClass() when this method fails: what started would outlive
            // the run and hold the CI step open.
            self::tearDownAfterClass();

            throw $e;
        }
    }

    private static function startTheShopAndTheHarness(): void
    {
        // Rebuilt first: the workers run without debug, so a stale container would go unnoticed.
        foreach (['demo', 'demo_caller'] as $env) {
            self::mustSucceed($env, ['bin/console', 'cache:clear', '--no-interaction']);
        }
        self::mustSucceed('demo', ['bin/console', 'doctrine:database:create', '--if-not-exists', '--no-interaction']);
        self::mustSucceed('demo', ['bin/console', 'doctrine:schema:update', '--force', '--complete', '--no-interaction']);
        // Before the worker starts, which keeps what it reads (demo/README.md); ids by hand, since
        // Doctrine feeds them from sequences on PostgreSQL and no column has a default.
        $code = self::$variant;
        self::mustSucceed('demo', ['bin/console', 'dbal:run-sql', 'INSERT INTO sylius_product (id, code, created_at, enabled, variant_selection_method, average_rating) '
            . "SELECT COALESCE(MAX(id), 0) + 1, '{$code}', NOW(), true, 'choice', 0 FROM sylius_product"]);
        self::mustSucceed('demo', ['bin/console', 'dbal:run-sql', 'INSERT INTO sylius_product_variant (id, product_id, code, created_at, position, enabled, version, on_hold, on_hand, tracked, shipping_required, recurring) '
            . "SELECT (SELECT COALESCE(MAX(id), 0) + 1 FROM sylius_product_variant), id, '{$code}', NOW(), 0, true, 1, 0, " . self::ON_HAND . ", true, true, false FROM sylius_product WHERE code = '{$code}'"]);

        $harness = self::spawn('harness', 'demo', [
            self::harnessScript(),
            self::$connection->target,
            self::$connection->namespace->name(),
            '--bench=stock@' . self::$connection->nexusTaskQueue->name(),
            '--transport=' . self::$connection->transport,
        ]);
        $deadline = microtime(true) + 60.0;
        while (!str_contains((string) file_get_contents($harness['log']), 'ready')) {
            if (microtime(true) > $deadline || !proc_get_status($harness['process'])['running']) {
                self::fail("The demo harness never said ready:\n" . file_get_contents($harness['log']));
            }
            usleep(200_000);
        }

        self::spawn('serves-stock', 'demo', ['bin/console', 'messenger:consume', 'durable_nexus', '--no-interaction']);
        self::spawn('runs-workflows', 'demo_caller', ['bin/console', 'messenger:consume', 'durable_workflows', '--no-interaction']);
        sleep(3); // for a worker whose receiver is missing to die before the first test looks
    }

    protected function setUp(): void
    {
        foreach (self::$processes as ['name' => $name, 'process' => $process]) {
            self::assertTrue(proc_get_status($process)['running'], "The {$name} process stopped." . self::logs());
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$processes as ['name' => $name, 'process' => $process]) {
            // SIGTERM lets the harness delete its endpoints; a worker holds nothing worth the wait.
            proc_terminate($process, 'harness' === $name ? \SIGTERM : \SIGKILL);
        }
        array_map(static fn(array $p): int => proc_close($p['process']), self::$processes);
        self::$processes = [];
    }

    public function testStockHandlerHoldsWhatItReserves(): void
    {
        $code = self::$variant;
        self::assertSame(['reserved' => true, 'missing' => []], $this->call('stock/reserve', ['order' => 'ORD-S1-' . $code, 'lines' => [$code => 2]]));
        // Three left once two are held: only StockHandler, reading the shop's stock, says one is
        // missing. The harness reserves everything.
        self::assertSame(['reserved' => false, 'missing' => [$code => 1]], $this->call('stock/reserve', ['order' => 'ORD-S2-' . $code, 'lines' => [$code => 4]]));
    }

    public function testOrderWorkflowVerifiesThenChargesThroughBilling(): void
    {
        $order = 'BILL-' . self::$variant;
        $run = self::execute('demo_caller', ['bin/console', 'durable:demo:bill', $order, '1200', '--timeout=90', '--no-interaction']);

        self::assertSame(0, $run['code'], $run['output'] . self::logs());
        // OrderOutcome's wire shape around the harness's answers: verify, then charge, on one stub.
        $json = substr($run['output'], (int) strpos($run['output'], '{'), (int) strrpos($run['output'], '}') - (int) strpos($run['output'], '{') + 1);
        self::assertSame([
            'verified' => ['accepted' => true, 'reason' => null],
            'charge' => ['receipt' => 'RCP-' . $order, 'charged' => 1200],
        ], json_decode($json, true, flags: \JSON_THROW_ON_ERROR), $run['output']);
    }

    /** @param array<string, mixed> $payload */
    private function call(string $operation, array $payload): mixed
    {
        $run = self::execute('demo', [
            self::harnessScript(),
            self::$connection->target,
            self::$connection->namespace->name(),
            '--call=' . $operation,
            '--input=' . json_encode($payload, \JSON_THROW_ON_ERROR),
            '--transport=' . self::$connection->transport,
        ]);
        self::assertSame(0, $run['code'], $run['output'] . self::logs());

        return json_decode($run['output'], true, flags: \JSON_THROW_ON_ERROR);
    }

    private static function harnessScript(): string
    {
        return \dirname(__DIR__, 3) . '/tests/integration/Temporal/nexus-demo-harness.php';
    }

    /** @param list<string> $command */
    private static function mustSucceed(string $env, array $command): void
    {
        $run = self::execute($env, $command);
        self::assertSame(0, $run['code'], implode(' ', $command) . "\n" . $run['output']);
    }

    /**
     * @param list<string> $command a PHP script and its arguments, run from the shop's directory
     * @return array{code: int, output: string}
     */
    private static function execute(string $env, array $command): array
    {
        $process = proc_open([\PHP_BINARY, ...$command], [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, \dirname(__DIR__, 2), self::environment($env));
        self::assertIsResource($process);
        // One stream: reading stdout to its end first would block a child that fills stderr.
        $output = (string) stream_get_contents($pipes[1]);

        return ['code' => proc_close($process), 'output' => $output];
    }

    /**
     * @param list<string> $command
     * @return array{name: string, process: resource, log: string}
     */
    private static function spawn(string $name, string $env, array $command): array
    {
        $log = sys_get_temp_dir() . '/durable-nexus-shop-' . $name . '-' . getmypid() . '.log';
        is_file($log) && unlink($log);
        $process = proc_open([\PHP_BINARY, ...$command], [1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, \dirname(__DIR__, 2), self::environment($env));
        self::assertIsResource($process);

        return self::$processes[] = ['name' => $name, 'process' => $process, 'log' => $log];
    }

    /** @return array<string, string> */
    private static function environment(string $env): array
    {
        return ['APP_ENV' => $env, 'APP_DEBUG' => '0', 'DURABLE_TEMPORAL_DSN' => self::$dsn, 'DATABASE_URL' => self::$database] + getenv();
    }

    private static function logs(): string
    {
        $logs = '';
        foreach (self::$processes as ['log' => $log]) {
            $logs .= "\n--- {$log}\n" . substr((string) file_get_contents($log), -3000);
        }

        return $logs;
    }
}
