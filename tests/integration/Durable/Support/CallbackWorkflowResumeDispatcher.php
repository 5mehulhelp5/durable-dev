<?php

declare(strict_types=1);

namespace integration\Durable\Support;

use Gplanchat\Durable\Port\WorkflowResumeDispatcher;
use Gplanchat\Durable\Transport\AwaitedFact;

/**
 * Test dispatcher: explicit callbacks to simulate the resume without Messenger.
 */
final class CallbackWorkflowResumeDispatcher implements WorkflowResumeDispatcher
{
    public function __construct(
        private readonly \Closure $onResume,
        private readonly ?\Closure $onNew = null,
    ) {}

    /**
     * @param list<array{name: string, arguments: array<string, mixed>}> $pendingUpdates
     */
    public function dispatchResume(string $executionId, array $pendingUpdates = []): void
    {
        ($this->onResume)($executionId, $pendingUpdates);
    }

    /**
     * Nothing to announce early: the callback runs the resume inline, which is what a `sync` route
     * does, and the resume after the append does the work (DUR050).
     */
    public function dispatchResumeAwaiting(string $executionId, AwaitedFact $fact): void {}

    public function dispatchNewWorkflowRun(string $executionId, string $workflowType, array $payload): void
    {
        if (null !== $this->onNew) {
            ($this->onNew)($executionId, $workflowType, $payload);
        }
    }
}
