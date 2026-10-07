<?php

declare(strict_types=1);

namespace unit\Gplanchat\Bridge\Temporal\Worker;

use Gplanchat\Bridge\Temporal\TemporalConnection;
use Gplanchat\Bridge\Temporal\Worker\TemporalChildWorkflowRunner;
use Gplanchat\Bridge\Temporal\Worker\TemporalExecutionHistory;
use Gplanchat\Bridge\Temporal\Worker\TemporalWorkflowCommandBuffer;
use Gplanchat\Durable\Exception\DurableChildWorkflowFailedException;
use Gplanchat\Durable\ExecutionContext;
use Gplanchat\Durable\ExecutionId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\ChildWorkflowExecutionCanceledEventAttributes;
use Temporal\Api\History\V1\ChildWorkflowExecutionFailedEventAttributes;
use Temporal\Api\History\V1\ChildWorkflowExecutionTerminatedEventAttributes;
use Temporal\Api\History\V1\ChildWorkflowExecutionTimedOutEventAttributes;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\History\V1\StartChildWorkflowExecutionFailedEventAttributes;
use Temporal\Api\History\V1\StartChildWorkflowExecutionInitiatedEventAttributes;

/**
 * A child that never produces a result must still release its parent, with the failure the journal
 * backends raise (#980). The history reader settled a child on COMPLETED and FAILED only: a start
 * the server refused, or a child that timed out, was cancelled or was terminated, left the parent
 * waiting for good.
 */
final class AChildThatEndsUnhappilySettlesItsParentTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function theUnhappyEndings(): iterable
    {
        yield 'the child failed' => ['failed'];
        yield 'the start was refused' => ['start_failed'];
        yield 'the child timed out' => ['timed_out'];
        yield 'the child was cancelled' => ['canceled'];
        yield 'the child was terminated' => ['terminated'];
    }

    #[DataProvider('theUnhappyEndings')]
    public function testTheHistoryReportsTheChildAsFailedWithTheJournalsException(string $ending): void
    {
        $history = TemporalExecutionHistory::fromEvents([$this->initiated('child-1'), $this->ending($ending, 'child-1')]);

        $outcome = $history->findChildWorkflowForSlot(0);

        self::assertNotNull($outcome, 'the parent would wait for ever');
        self::assertInstanceOf(DurableChildWorkflowFailedException::class, $outcome->failed);
        self::assertSame('child-1', $outcome->failed->childExecutionId);
    }

    #[DataProvider('theUnhappyEndings')]
    public function testTheParentsAwaitIsSettledOnReplay(string $ending): void
    {
        $history = TemporalExecutionHistory::fromEvents([$this->initiated('child-1'), $this->ending($ending, 'child-1')]);
        $context = new ExecutionContext(
            ExecutionId::fromString('parent-1'),
            $history,
            new TemporalWorkflowCommandBuffer(new TemporalConnection('localhost:7233', 'test'), ExecutionId::fromString('parent-1')),
            new TemporalChildWorkflowRunner(),
        );

        $awaitable = $context->executeChildWorkflow('ChildType', []);

        self::assertTrue($awaitable->isSettled());
    }

    // -------------------------------------------------------------------------

    private function initiated(string $childWorkflowId): HistoryEvent
    {
        $attrs = new StartChildWorkflowExecutionInitiatedEventAttributes();
        $attrs->setWorkflowId($childWorkflowId);

        $event = new HistoryEvent();
        $event->setEventId(5);
        $event->setEventType(EventType::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED);
        $event->setStartChildWorkflowExecutionInitiatedEventAttributes($attrs);

        return $event;
    }

    private function ending(string $ending, string $childWorkflowId): HistoryEvent
    {
        $execution = new WorkflowExecution(['workflow_id' => $childWorkflowId]);
        $event = new HistoryEvent();
        $event->setEventId(9);

        switch ($ending) {
            case 'failed':
                $event->setEventType(EventType::EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_FAILED);
                $event->setChildWorkflowExecutionFailedEventAttributes((new ChildWorkflowExecutionFailedEventAttributes())->setWorkflowExecution($execution));
                break;
            case 'start_failed':
                $event->setEventType(EventType::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_FAILED);
                $event->setStartChildWorkflowExecutionFailedEventAttributes((new StartChildWorkflowExecutionFailedEventAttributes())->setWorkflowId($childWorkflowId));
                break;
            case 'timed_out':
                $event->setEventType(EventType::EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_TIMED_OUT);
                $event->setChildWorkflowExecutionTimedOutEventAttributes((new ChildWorkflowExecutionTimedOutEventAttributes())->setWorkflowExecution($execution));
                break;
            case 'canceled':
                $event->setEventType(EventType::EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_CANCELED);
                $event->setChildWorkflowExecutionCanceledEventAttributes((new ChildWorkflowExecutionCanceledEventAttributes())->setWorkflowExecution($execution));
                break;
            case 'terminated':
                $event->setEventType(EventType::EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_TERMINATED);
                $event->setChildWorkflowExecutionTerminatedEventAttributes((new ChildWorkflowExecutionTerminatedEventAttributes())->setWorkflowExecution($execution));
                break;
        }

        return $event;
    }
}
