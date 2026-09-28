<?php

declare(strict_types=1);

namespace integration\Durable\Crash;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * DUR052: the crash bench of {@see ResumeDispatchedFirstCrashTest}, on the paths beyond activities.
 * Each step is its own PHP process over one SQLite file; the killed process `SIGKILL`s itself right
 * after the append under test, and acknowledges its message on dequeue so that nothing redelivers
 * it: only the resume sent before the append can carry the run.
 *
 * @internal
 */
final class ResumeProtocolBeyondActivitiesCrashTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        if (!\function_exists('posix_kill') || !\extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('the bench needs posix and pdo_sqlite: it kills a process and shares a journal file');
        }
        $this->directory = sys_get_temp_dir() . '/durable-resume-beyond-' . bin2hex(random_bytes(6));
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
     * The child's resume is acknowledged and the child dies right after its outcome reaches the
     * parent's journal: the resume naming the child, sent first, carries the parent to its end.
     */
    #[Test]
    public function aChildKilledAfterReportingToItsParentLeavesTheParentToTheResumeSentFirst(): void
    {
        $this->step('start-parent');
        $this->step('workflow', ['SLICE_ONE' => '1']); // the parent starts its child and waits

        self::assertNotSame(0, $this->step('workflow', ['SLICE_KILL' => 'after-child-append', 'SLICE_ACK' => 'on-dequeue'])['code'], 'the child must die');

        $this->step('workflow');

        self::assertSame('completed', trim($this->step('status', ['SLICE_EXECUTION' => 'parent'])['stdout']));
    }

    /**
     * The fire message is acknowledged and the timer worker dies right after `TimerCompleted`:
     * the resume naming the timer, sent first, carries the run to its end.
     */
    #[Test]
    public function aTimerWorkerKilledAfterFiringLeavesTheRunToTheResumeSentFirst(): void
    {
        $this->step('start-timer');
        $this->step('workflow'); // the run schedules its timer and waits
        usleep(300_000);         // past the timer's deadline

        self::assertNotSame(0, $this->step('timer', ['SLICE_KILL' => 'after-timer-append', 'SLICE_ACK' => 'on-dequeue'])['code'], 'the timer worker must die');

        $this->step('workflow');

        self::assertSame('completed', trim($this->step('status', ['SLICE_EXECUTION' => 'timer'])['stdout']));
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
}
