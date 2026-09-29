<?php

declare(strict_types=1);

namespace Tests;

use Gplanchat\Bridge\Temporal\TemporalConnection;
use PHPUnit\Framework\TestCase;

/**
 * The logistics serves `delivery` against a server, through its own workers (#666).
 *
 * `config/durable.php` declares DeliveryHandler, which answers `schedule` now, and ShipWorkflow,
 * which fulfils `ship` and calls `stock/reserve` on its way. `artisan durable:nexus-worker` and
 * `durable:temporal-worker` run them. The root's demo harness (#663) serves `stock` and `billing`,
 * routes `delivery` to this bench's Nexus queue, and calls it. Each answer asserted here is one only
 * the bench gives. Needs a server with Nexus on and Durable's search attributes (the bench writes
 * them), the root's vendor/, and DURABLE_DSN with the bench's `nexus_task_queue`.
 */
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
            $harness = self::spawn('harness', [
                \dirname(__DIR__, 2) . '/tests/integration/Temporal/nexus-demo-harness.php',
                self::$connection->target,
                self::$connection->namespace->name(),
                '--bench=delivery@' . self::$connection->nexusTaskQueue->name(),
                '--transport=' . self::$connection->transport,
            ]);
            $deadline = microtime(true) + 60.0;
            while (1 !== preg_match('/^ready$/m', (string) file_get_contents($harness['log']))) {
                if (microtime(true) > $deadline || !proc_get_status($harness['process'])['running']) {
                    self::fail("The demo harness never said ready:\n" . file_get_contents($harness['log']));
                }
                usleep(200_000);
            }
            self::spawn('serves-delivery', ['artisan', 'durable:nexus-worker']);
            self::spawn('runs-workflows', ['artisan', 'durable:temporal-worker', '--role=workflow']);
            sleep(3); // for a worker that fails at boot to die before the first test looks
        } catch (\Throwable $e) {
            // PHPUnit skips tearDownAfterClass() when this method fails: what started would outlive
            // the run and hold the CI step open.
            self::tearDownAfterClass();

            throw $e;
        }
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

    public function testDeliveryHandlerSchedulesOnTheTask(): void
    {
        $order = 'SCH-' . bin2hex(random_bytes(3));
        // Three parcels go by freight, on tomorrow's date: the harness would say courier, next-day.
        $scheduled = $this->call('delivery/schedule', ['order' => $order . '-1', 'lines' => ['MUG_BLUE' => 3]]);
        self::assertSame(['scheduled' => true, 'carrier' => 'freight', 'reason' => null], array_diff_key($scheduled, ['slot' => true]));
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} 09:00-12:00$/', $scheduled['slot']);

        self::assertSame(
            ['scheduled' => false, 'slot' => '', 'carrier' => '', 'reason' => '6 parcels, 5 at most per round'],
            $this->call('delivery/schedule', ['order' => $order . '-2', 'lines' => ['MUG_BLUE' => 6]]),
        );
    }

    public function testShipWorkflowFulfilsShipAndCallsTheShopOnItsWay(): void
    {
        $order = 'SHIP-' . bin2hex(random_bytes(3));
        $slot = '2026-10-01 09:00-12:00';

        // ShipWorkflow's tracking number, after the shop (the harness) held the stock. The harness's
        // own stand-in would answer TRK-<order>.
        self::assertSame(
            ['shipped' => true, 'tracking' => 'TRK-' . strtoupper(substr(md5($order . $slot), 0, 10))],
            $this->call('delivery/ship', ['order' => $order, 'slot' => $slot]),
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function call(string $operation, array $payload): array
    {
        $process = proc_open([
            \PHP_BINARY,
            \dirname(__DIR__, 2) . '/tests/integration/Temporal/nexus-demo-harness.php',
            self::$connection->target,
            self::$connection->namespace->name(),
            '--call=' . $operation,
            '--input=' . json_encode($payload, \JSON_THROW_ON_ERROR),
            '--transport=' . self::$connection->transport,
        ], [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, \dirname(__DIR__), self::environment());
        self::assertIsResource($process);
        // One stream: reading stdout to its end first would block a child that fills stderr.
        $output = (string) stream_get_contents($pipes[1]);
        self::assertSame(0, proc_close($process), $output . self::logs());

        return json_decode((string) strrchr(trim($output), "\n") ?: $output, true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $command
     *
     * @return array{name: string, process: resource, log: string}
     */
    private static function spawn(string $name, array $command): array
    {
        $log = sys_get_temp_dir() . '/durable-nexus-logistics-' . $name . '-' . getmypid() . '.log';
        is_file($log) && unlink($log);
        $process = proc_open([\PHP_BINARY, ...$command], [1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, \dirname(__DIR__), self::environment());
        self::assertIsResource($process);

        return self::$processes[] = ['name' => $name, 'process' => $process, 'log' => $log];
    }

    /** @return array<string, string> the array cache: the handler's rememberForever stays per process */
    private static function environment(): array
    {
        return ['DURABLE_DSN' => self::$dsn, 'CACHE_STORE' => 'array'] + getenv();
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
