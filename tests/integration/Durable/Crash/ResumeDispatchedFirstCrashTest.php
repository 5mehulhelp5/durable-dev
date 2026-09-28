<?php

declare(strict_types=1);

namespace integration\Durable\Crash;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * #328, DUR050: the activity worker sends the resume before it appends the outcome, and again after.
 *
 * Each step runs in its own PHP process over one SQLite file, which holds the journal and two
 * queues. A queued message is deleted only once its process is done with it, so a killed process
 * leaves it there, as an unacknowledged message is left on a real transport. The kills are
 * `SIGKILL`s from inside the activity worker, at the instant under test.
 *
 * @internal
 */
final class ResumeDispatchedFirstCrashTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        if (!\function_exists('posix_kill') || !\extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('the bench needs posix and pdo_sqlite: it kills a process and shares a journal file');
        }
        $this->directory = sys_get_temp_dir() . '/durable-resume-first-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0o700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    /**
     * The crash #328 describes: the outcome is journalled and the worker dies before the resume
     * that follows it. The activity message is not redelivered here (an inline drain acknowledged
     * it on dequeue, or the transport waits an hour before it redelivers): the resume sent first
     * is what carries the run to its end.
     */
    #[Test]
    public function aWorkerKilledAfterTheAppendLeavesTheRunToTheResumeSentFirst(): void
    {
        $this->step('start');
        $this->step('workflow');

        self::assertNotSame(0, $this->step('activity', ['SLICE_KILL' => 'after-append', 'SLICE_ACK' => 'on-dequeue'])['code'], 'the activity worker must die');
        self::assertSame(1, $this->queued('resumes'), 'the resume sent before the append survived the worker');

        $this->step('workflow');

        self::assertSame('completed', trim($this->step('status')['stdout']));
        self::assertSame(['charge'], $this->activitiesRun(), 'the activity ran once');
    }

    /**
     * Killed after the send and before the append: the resume arrives first and waits, the
     * activity message is redelivered and runs again (at-least-once), and the run completes.
     */
    #[Test]
    public function aResumeThatArrivesBeforeItsOutcomeWaitsForTheRedelivery(): void
    {
        $this->step('start');
        $this->step('workflow');

        self::assertNotSame(0, $this->step('activity', ['SLICE_KILL' => 'before-append'])['code'], 'the activity worker must die');
        self::assertSame(3, $this->step('workflow')['code'], 'the resume waits: its outcome is not journalled');
        self::assertSame('running', trim($this->step('status')['stdout']));

        self::assertSame(0, $this->step('activity')['code'], 'the redelivered message runs the attempt again');
        $this->step('workflow');

        self::assertSame('completed', trim($this->step('status')['stdout']));
        self::assertSame(['charge', 'charge'], $this->activitiesRun(), 'at-least-once: the killed attempt ran, and so did its redelivery');
    }

    /**
     * @param array<string, string> $env
     *
     * @return array{code: int, stdout: string, stderr: string}
     */
    private function step(string $step, array $env = []): array
    {
        $process = proc_open(
            [\PHP_BINARY, __DIR__ . '/resume_first_slice.php', $this->directory . '/journal.sqlite', $step],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['SLICE_LOG' => $this->directory . '/activities.log', ...$env] + getenv(),
        );
        self::assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['code' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private function queued(string $queue): int
    {
        return (int) trim($this->step('count-' . $queue)['stdout']);
    }

    /**
     * @return list<string>
     */
    private function activitiesRun(): array
    {
        $log = $this->directory . '/activities.log';

        return is_file($log) ? array_values(array_filter(explode("\n", (string) file_get_contents($log)))) : [];
    }
}
