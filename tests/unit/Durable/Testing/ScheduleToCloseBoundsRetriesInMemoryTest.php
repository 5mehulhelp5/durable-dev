<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable\Testing;

use Gplanchat\Durable\Activity\ActivityOptions;
use Gplanchat\Durable\Activity\ActivityTimeouts;
use Gplanchat\Durable\Activity\RetryLimit;
use Gplanchat\Durable\Attribute\AsActivityMethod;
use Gplanchat\Durable\Duration;
use Gplanchat\Durable\ExecutionId;
use Gplanchat\Durable\Testing\WorkflowTestEnvironment;
use Gplanchat\Durable\WorkflowEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * The in-memory runner's time stands still between timer skips, but a retry's backoff is waited
 * out for real: that wait counts towards schedule-to-close, or the bound never trips (#617).
 */
final class ScheduleToCloseBoundsRetriesInMemoryTest extends TestCase
{
    public function testTheBackoffWaitedOutCountsTowardsScheduleToClose(): void
    {
        $attempts = 0;
        $env = WorkflowTestEnvironment::inMemory(['flaky' => static function () use (&$attempts): never {
            ++$attempts;

            throw new \RuntimeException('boom');
        }]);

        try {
            $env->run(static fn(WorkflowEnvironment $wf): mixed => $wf->await($wf->activityStub(FlakyActivities::class, new ActivityOptions(
                retryLimit: RetryLimit::ofAttempts(8),
                initialInterval: Duration::seconds(0.2),
                backoffCoefficient: 1.0,
                timeouts: new ActivityTimeouts(scheduleToClose: Duration::seconds(0.3)),
            ))->flaky()));
            self::fail('the activity never succeeds');
        } catch (\Throwable $e) {
            self::assertStringContainsString('schedule-to-close', $e->getMessage());
        }
        self::assertSame(2, $attempts, 'the third attempt starts past the 0.3 s bound');
    }

    /**
     * A timer that falls due during a backoff is recorded once the drain is idle: the activity's
     * remaining attempts run first, so a retrying activity still wins `any()`, and the losing
     * timer is journalled as completed after it (it used to be cancelled).
     */
    public function testATimerDueDuringABackoffIsRecordedAfterTheRetryingActivityWins(): void
    {
        $attempts = 0;
        $env = WorkflowTestEnvironment::inMemory(['flaky' => static function () use (&$attempts): string {
            if (++$attempts < 3) {
                throw new \RuntimeException('boom');
            }

            return 'charged';
        }]);

        $result = $env->run(static fn(WorkflowEnvironment $wf): mixed => $wf->await($wf->any(
            $wf->activityStub(FlakyActivities::class, new ActivityOptions(
                retryLimit: RetryLimit::ofAttempts(5),
                initialInterval: Duration::seconds(0.2),
                backoffCoefficient: 1.0,
            ))->flaky(),
            $wf->timer(0.1),
        )), 'exec-race');

        $recorded = [];
        foreach ($env->getEventStore()->readStream(ExecutionId::fromString('exec-race')) as $event) {
            $name = (new \ReflectionClass($event))->getShortName();
            if (\in_array($name, ['ActivityCompleted', 'TimerCompleted', 'TimerCancelled'], true)) {
                $recorded[] = $name;
            }
        }

        self::assertSame('charged', $result);
        self::assertSame(3, $attempts);
        self::assertSame(['ActivityCompleted', 'TimerCompleted'], $recorded);
    }
}

interface FlakyActivities
{
    #[AsActivityMethod('flaky')]
    public function flaky(): string;
}
