<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable\Laravel;

use Gplanchat\Durable\Laravel\Queue\InProcessWorkflowResumeDispatcher;
use Gplanchat\Durable\Store\InMemoryWorkflowMetadataStore;
use Gplanchat\Durable\Transport\ActivityMessage;
use Gplanchat\Durable\Transport\InMemoryActivityTransport;
use Gplanchat\Durable\Transport\ResumeWorkflowMessage;
use PHPUnit\Framework\TestCase;

/**
 * #603: on Laravel's memory backend a run is driven in the caller's process. A resume dispatched
 * while a resume runs is queued and run after it, never inside it.
 */
final class TheInProcessDispatcherDrainsWithoutRecursionTest extends TestCase
{
    private ?InProcessWorkflowResumeDispatcher $subject = null;
    private int $depth = 0;
    private int $deepest = 0;

    /** @var list<string> */
    private array $ran = [];

    public function testAResumeDispatchedDuringAResumeRunsAfterItNotInsideIt(): void
    {
        $dispatcher = $this->subject = $this->dispatcher(resume: function (ResumeWorkflowMessage $message): void {
            $this->enter('resume ' . $message->executionId);
            if ('exec-1' === $message->executionId) {
                $this->subject?->dispatchResume('exec-2');
            }
            --$this->depth;
        });

        $dispatcher->dispatchNewWorkflowRun('exec-1', 'Greeting', []);

        self::assertSame(['resume exec-1', 'resume exec-2'], $this->ran);
        self::assertSame(1, $this->deepest, 'no resume ran inside another');
    }

    public function testAnActivityQueuedByAResumeRunsAndItsResumeFollows(): void
    {
        $activities = new InMemoryActivityTransport();
        $dispatcher = $this->subject = $this->dispatcher(
            resume: function (ResumeWorkflowMessage $message) use ($activities): void {
                $this->ran[] = 'resume ' . $message->executionId;
                if (1 === \count($this->ran)) {
                    $activities->enqueue(new ActivityMessage($message->executionId, 'act-1', 'charge', []));
                }
            },
            activity: function (ActivityMessage $message): void {
                $this->ran[] = 'activity ' . $message->activityId;
                $this->subject?->dispatchResume($message->executionId);
            },
            activities: $activities,
        );

        $dispatcher->dispatchNewWorkflowRun('exec-1', 'Greeting', []);

        self::assertSame(['resume exec-1', 'activity act-1', 'resume exec-1'], $this->ran);
    }

    public function testAFailedResumeLeavesTheDispatcherReadyForTheNextOne(): void
    {
        $dispatcher = $this->dispatcher(resume: function (ResumeWorkflowMessage $message): void {
            $this->ran[] = 'resume ' . $message->executionId;
            if ('exec-1' === $message->executionId) {
                throw new \RuntimeException('boom');
            }
        });

        try {
            $dispatcher->dispatchResume('exec-1');
            self::fail('the failure reaches the caller');
        } catch (\RuntimeException) {
        }
        $dispatcher->dispatchResume('exec-2');

        self::assertSame(['resume exec-1', 'resume exec-2'], $this->ran, 'the second dispatch still drains');
    }

    private function enter(string $what): void
    {
        $this->ran[] = $what;
        $this->deepest = max($this->deepest, ++$this->depth);
    }

    private function dispatcher(
        ?\Closure $resume = null,
        ?\Closure $activity = null,
        ?InMemoryActivityTransport $activities = null,
        float $budget = 2.0,
    ): InProcessWorkflowResumeDispatcher {
        $none = static function (): void {};

        return new InProcessWorkflowResumeDispatcher(
            new InMemoryWorkflowMetadataStore(),
            $activities ?? new InMemoryActivityTransport(),
            static fn(): \Closure => $resume ?? $none,
            static fn(): \Closure => $activity ?? $none,
            $budget,
        );
    }
}
