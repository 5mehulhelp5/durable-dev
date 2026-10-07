<?php

declare(strict_types=1);

namespace Gplanchat\DurableModule\Runtime;

use Gplanchat\Durable\ExecutionId;
use Gplanchat\Durable\Port\WorkflowResumeDispatcher;
use Gplanchat\Durable\Transport\AwaitedFact;

/**
 * The start of a run without a cluster (#976): the in-memory journal lives in this process, so the
 * process that starts a run executes it, in `dispatchNewWorkflowRun()`, before it returns.
 *
 * `MagentoRuntime::run()` does the work and bounds it with `budgetSeconds`. A run that fails
 * throws from the dispatch. The resumes are no-ops: nothing else advances a run, and `run()`
 * already drove it to its end.
 *
 * ponytail: it blocks the request, as `run()` does. A web request that must not wait needs the
 * cluster (`durable/temporal/dsn`).
 */
final class InProcessWorkflowResumeDispatcher implements WorkflowResumeDispatcher
{
    public function __construct(private readonly MagentoRuntime $runtime) {}

    public function dispatchResume(ExecutionId $executionId, array $pendingUpdates = []): void {}

    public function dispatchResumeAwaiting(ExecutionId $executionId, AwaitedFact $fact): void {}

    public function dispatchNewWorkflowRun(ExecutionId $executionId, string $workflowType, array $payload): void
    {
        /** @var class-string $workflowType */
        $this->runtime->run($workflowType, $payload, $executionId->toString());
    }
}
