<?php

declare(strict_types=1);

namespace App\Tests\Integration\Temporal;

use Gplanchat\Bridge\Temporal\TemporalConnection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The bench serves `billing` and calls `stock`, against a server, through its own workers (#664).
 *
 * The other side is the root's demo harness (#663): it serves `stock` and `delivery`, creates the
 * `billing` endpoint toward this bench's Nexus queue, and calls `billing` for the tests. Each answer
 * asserted here is one only the bench can give: the harness's own would read differently.
 *
 * Needs a server with Nexus on — `start-dev`, not the compose stack's auto-setup 1.25.2 — the root's
 * vendor/ for the harness, and DURABLE_DSN naming that server. Its own group, so the jobs that run
 * this directory against the compose stack and the TLS proxy can leave it out.
 */
#[Group('nexus-bench')]
final class NexusBenchTest extends TestCase
{
    /** @var list<array{name: string, process: resource, log: string}> */
    private static array $processes = [];
    private static TemporalConnection $connection;
    private static string $dsn;

    public static function setUpBeforeClass(): void
    {
        self::$dsn = (string) (getenv('DURABLE_DSN') ?: '');
        if ('' === self::$dsn) {
            self::markTestSkipped('Set DURABLE_DSN to a Temporal server with Nexus on (temporal server start-dev).');
        }
        self::$connection = TemporalConnection::fromDsn(self::$dsn);

        try {
            self::startTheBenchAndTheHarness();
        } catch (\Throwable $e) {
            // PHPUnit skips tearDownAfterClass() when this method fails: what started would outlive
            // the run and hold the CI step open.
            self::tearDownAfterClass();

            throw $e;
        }
    }

    private static function startTheBenchAndTheHarness(): void
    {
        // Rebuilt once: the workers run without debug, so a stale dev container would go unnoticed,
        // and three of them compiling it at the same time would race.
        self::assertSame(0, self::execute(['bin/console', 'cache:clear', '--no-interaction'])['code']);

        $harness = self::spawn('harness', [
            \dirname(__DIR__, 4) . '/tests/integration/Temporal/nexus-demo-harness.php',
            self::$connection->target,
            self::$connection->namespace->name(),
            '--bench=billing@' . self::$connection->nexusTaskQueue->name(),
            '--transport=' . self::$connection->transport,
        ]);
        $deadline = microtime(true) + 60.0;
        while (!str_contains((string) file_get_contents($harness['log']), 'ready')) {
            if (microtime(true) > $deadline || !proc_get_status($harness['process'])['running']) {
                self::fail("The demo harness never said ready:\n" . file_get_contents($harness['log']));
            }
            usleep(200_000);
        }

        foreach (['durable_nexus', 'durable_workflows', 'durable_activities'] as $transport) {
            self::spawn($transport, ['bin/console', 'messenger:consume', $transport, '--no-interaction']);
        }
        // Long enough for a worker whose receiver is missing to die before the first test looks.
        sleep(3);
    }

    protected function setUp(): void
    {
        // A worker that died at boot — a receiver the container no longer has — fails here, with
        // its log, rather than as a call nobody answers.
        foreach (self::$processes as ['name' => $name, 'process' => $process]) {
            self::assertTrue(proc_get_status($process)['running'], "The {$name} process stopped." . self::logs());
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$processes as ['name' => $name, 'process' => $process]) {
            // SIGTERM lets the harness delete its endpoints; a worker would only stop after its
            // current long poll, and holds nothing worth the wait.
            proc_terminate($process, 'harness' === $name ? \SIGTERM : \SIGKILL);
        }
        foreach (self::$processes as ['process' => $process]) {
            proc_close($process);
        }
        self::$processes = [];
    }

    public function testTheBillingHandlerAnswersVerifyAtOnce(): void
    {
        self::assertSame(['accepted' => true, 'reason' => null], $this->call('billing/verify', ['order' => 'ORD-V1', 'amount' => 1200, 'currency' => 'EUR']));
        // The harness accepts everything: a refusal can only come from BillingHandler.
        self::assertSame(['accepted' => false, 'reason' => 'currency USD is not supported'], $this->call('billing/verify', ['order' => 'ORD-V2', 'amount' => 1200, 'currency' => 'USD']));
    }

    public function testChargeWorkflowFulfilsChargeThroughItsActivity(): void
    {
        // RECEIPT-…: ChargeActivityHandler's receipt, after ChargeWorkflow's twelve seconds. The
        // harness would answer RCP-….
        self::assertSame(['receipt' => 'RECEIPT-EUR-ORD-C1', 'charged' => 1200], $this->call('billing/charge', ['order' => 'ORD-C1', 'amount' => 1200, 'currency' => 'EUR']));
    }

    public function testReserveStockWorkflowCallsTheShop(): void
    {
        $order = 'ORD-R' . bin2hex(random_bytes(3));
        $run = self::execute(['bin/console', 'durable:demo:nexus', $order, 'SKU-1=1', '--timeout=90', '--no-interaction']);

        self::assertSame(0, $run['code'], $run['output'] . self::logs());
        self::assertStringContainsString('The shop held the stock.', $run['output'], self::logs());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function call(string $operation, array $payload): mixed
    {
        $run = self::execute([
            \dirname(__DIR__, 4) . '/tests/integration/Temporal/nexus-demo-harness.php',
            self::$connection->target,
            self::$connection->namespace->name(),
            '--call=' . $operation,
            '--input=' . json_encode($payload, \JSON_THROW_ON_ERROR),
            '--transport=' . self::$connection->transport,
        ]);
        self::assertSame(0, $run['code'], $run['output'] . self::logs());

        return json_decode($run['output'], true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $command a PHP script and its arguments, run from the bench's directory
     *
     * @return array{code: int, output: string}
     */
    private static function execute(array $command): array
    {
        $process = proc_open([\PHP_BINARY, ...$command], [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, \dirname(__DIR__, 3), self::environment());
        self::assertIsResource($process);
        // One stream: reading stdout to its end first would block a child that fills stderr.
        $output = (string) stream_get_contents($pipes[1]);

        return ['code' => proc_close($process), 'output' => $output];
    }

    /**
     * @param list<string> $command
     *
     * @return array{name: string, process: resource, log: string}
     */
    private static function spawn(string $name, array $command): array
    {
        $log = sys_get_temp_dir() . '/durable-nexus-bench-' . $name . '-' . getmypid() . '.log';
        is_file($log) && unlink($log);
        $process = proc_open([\PHP_BINARY, ...$command], [1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, \dirname(__DIR__, 3), self::environment());
        self::assertIsResource($process);

        return self::$processes[] = ['name' => $name, 'process' => $process, 'log' => $log];
    }

    /** @return array<string, string> the dev environment, where the bench's Temporal backend is on */
    private static function environment(): array
    {
        return ['APP_ENV' => 'dev', 'APP_DEBUG' => '0', 'DURABLE_DSN' => self::$dsn] + getenv();
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
