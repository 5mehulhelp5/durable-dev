<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable;

use Gplanchat\Durable\Event\ChildWorkflowCompleted;
use Gplanchat\Durable\Event\ChildWorkflowFailed;
use Gplanchat\Durable\Event\ExecutionCompleted;
use Gplanchat\Durable\Event\ExecutionStarted;
use Gplanchat\Durable\Event\WorkflowContinuedAsNew;
use Gplanchat\Durable\ExecutionId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/CrashingContinueAsNewChain.php';

/**
 * The process stops at one step of a continue-as-new, and the transport redelivers the resume of
 * the old run. Whatever the step, the chain goes on and the parent hears once from its end (#881).
 */
final class AContinueAsNewSurvivesACrashTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function crashPoints(): iterable
    {
        yield 'after the next run is linked' => ['link'];
        yield 'after the next run is saved' => ['save'];
        yield 'at the start of the next run' => ['append'];
        yield 'at the dispatch of the next run' => ['dispatch'];
        yield 'before the old run is marked completed' => ['before markCompleted'];
        yield 'after the old run is marked completed' => ['after markCompleted'];
        yield 'after the old run is unlinked' => ['unlink'];
    }

    #[DataProvider('crashPoints')]
    public function testTheChainEndsOnceWhereverTheCrash(string $point): void
    {
        $chain = new CrashingContinueAsNewChain($point);

        try {
            $chain->resume('child-1');
            self::fail("No crash at {$point}.");
        } catch (\LogicException $e) {
            self::assertSame($point, $e->getMessage());
        }
        $chain->resume('child-1');
        $chain->driveStartedRuns();

        $runs = array_values(array_unique($chain->startedRuns));
        self::assertCount(2, $runs, 'two runs follow child-1, each started once');
        foreach ($runs as $run) {
            self::assertCount(1, $chain->eventsOf($run, ExecutionStarted::class), "{$run} has one start");
        }
        self::assertCount(1, $chain->eventsOf('child-1', WorkflowContinuedAsNew::class));
        self::assertCount(1, $chain->eventsOf($runs[0], WorkflowContinuedAsNew::class));

        $outcomes = [...$chain->eventsOf('parent-1', ChildWorkflowCompleted::class), ...$chain->eventsOf('parent-1', ChildWorkflowFailed::class)];
        self::assertCount(1, $outcomes);
        self::assertInstanceOf(ChildWorkflowCompleted::class, $outcomes[0]);
        self::assertSame('child-1', $outcomes[0]->childExecutionId()->toString());
        self::assertSame('done at 2', $outcomes[0]->result());

        // A crash between markCompleted() and unlink() leaves a link on a completed run, which no
        // resume reads again: the review of #870 accepted it.
        $left = array_map(strval(...), $chain->links->getChildExecutionIdsForParent(ExecutionId::fromString('parent-1')));
        self::assertSame('after markCompleted' === $point ? ['child-1'] : [], $left);
    }

    /**
     * The first attempt dispatched the next run before it stopped, and the chain finished before
     * the redelivery came: the redelivery leaves the finished runs as they are.
     */
    public function testARedeliveryAfterTheChainEndedReopensNothing(): void
    {
        $chain = new CrashingContinueAsNewChain('before markCompleted');

        try {
            $chain->resume('child-1');
            self::fail('No crash before markCompleted().');
        } catch (\LogicException) {
        }
        $chain->driveStartedRuns();

        $chain->resume('child-1');
        $chain->driveStartedRuns();

        $runs = array_values(array_unique($chain->startedRuns));
        self::assertCount(2, $runs);
        foreach (['child-1', ...$runs] as $run) {
            self::assertTrue($chain->get(ExecutionId::fromString($run))['completed'] ?? false, "{$run} stays completed");
        }
        self::assertCount(1, $chain->eventsOf($runs[1], ExecutionCompleted::class), 'the last run completes once');
        self::assertCount(1, $chain->eventsOf('parent-1', ChildWorkflowCompleted::class));
    }
}
