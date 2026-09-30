<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable\Testing;

use Gplanchat\Durable\Event\Event;
use Gplanchat\Durable\Event\ExecutionStarted;
use Gplanchat\Durable\Event\WorkflowContinuedAsNew;
use Gplanchat\Durable\ExecutionId;
use Gplanchat\Durable\Testing\WorkflowTestEnvironment;
use PHPUnit\Framework\TestCase;
use unit\Durable\Fixtures\CounterWorkflow;

/**
 * A run that calls `continueAsNew()` hands over to the next one, as `ResumeWorkflowHandler` does on
 * the journal backends, and the caller gets the last run's result (#802).
 */
final class TheHarnessFollowsAContinueAsNewChainTest extends TestCase
{
    public function testTheCallerGetsTheLastRunsResultAfterTwoContinuations(): void
    {
        $env = WorkflowTestEnvironment::inMemory();

        $result = $env->runWorkflowClass(CounterWorkflow::class, ['n' => 0], 'counter-0');

        self::assertSame('done at 2', $result);
    }

    public function testEachRunNamesTheOneItContinues(): void
    {
        $env = WorkflowTestEnvironment::inMemory();

        $env->runWorkflowClass(CounterWorkflow::class, ['n' => 0], 'counter-0');

        $chain = ['counter-0'];
        while (null !== $next = $this->successorOf($env, $chain[\count($chain) - 1])) {
            $started = $this->eventsOf($env, $next)[0] ?? null;
            self::assertInstanceOf(ExecutionStarted::class, $started);
            self::assertSame($chain[\count($chain) - 1], $started->payload()['continuedFromExecutionId'] ?? null);
            $chain[] = $next;
        }

        self::assertCount(3, $chain);
    }

    private function successorOf(WorkflowTestEnvironment $env, string $executionId): ?string
    {
        foreach ($this->eventsOf($env, $executionId) as $event) {
            if ($event instanceof WorkflowContinuedAsNew) {
                return $event->newExecutionId();
            }
        }

        return null;
    }

    /**
     * @return list<Event>
     */
    private function eventsOf(WorkflowTestEnvironment $env, string $executionId): array
    {
        return iterator_to_array($env->getEventStore()->readStream(ExecutionId::fromString($executionId)), false);
    }
}
