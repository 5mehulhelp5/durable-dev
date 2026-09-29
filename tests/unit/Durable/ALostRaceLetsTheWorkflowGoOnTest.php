<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable;

use Gplanchat\Durable\Activity\ActivityOptions;
use Gplanchat\Durable\Activity\RetryLimit;
use Gplanchat\Durable\Attribute\AsActivityMethod;
use Gplanchat\Durable\Duration;
use Gplanchat\Durable\Testing\WorkflowTestEnvironment;
use Gplanchat\Durable\WorkflowEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * A timer that wins `any()` against an activity waiting out its backoff cancels it. The next
 * resume replays the race, and the loser's cancellation used to surface as an unhandled
 * `race_superseded` failure: the workflow could not go on after the race (#678).
 */
final class ALostRaceLetsTheWorkflowGoOnTest extends TestCase
{
    public function testTheWorkflowGoesOnAfterATimerBeatsARetryingActivity(): void
    {
        $env = WorkflowTestEnvironment::inMemory(['retrying' => static function (): never {
            throw new \RuntimeException('boom');
        }]);

        $result = $env->run(static function (WorkflowEnvironment $wf): string {
            $winner = $wf->await($wf->any(
                $wf->activityStub(RetryingActivities::class, new ActivityOptions(
                    retryLimit: RetryLimit::ofAttempts(5),
                    initialInterval: Duration::seconds(30),
                    backoffCoefficient: 1.0,
                ))->retrying(),
                $wf->timer(3600),
            ));
            $wf->await($wf->timer(1));

            return null === $winner ? 'after' : 'the activity won';
        }, 'lost-race');

        self::assertSame('after', $result);

        $recorded = [];
        foreach ($env->getEventStore()->readStream('lost-race') as $event) {
            $name = (new \ReflectionClass($event))->getShortName();
            if (\in_array($name, ['ActivityCancelled', 'TimerCompleted', 'ExecutionCompleted'], true)) {
                $recorded[] = $name;
            }
        }
        self::assertSame(['TimerCompleted', 'ActivityCancelled', 'TimerCompleted', 'ExecutionCompleted'], $recorded);
    }
}

interface RetryingActivities
{
    #[AsActivityMethod('retrying')]
    public function retrying(): string;
}
