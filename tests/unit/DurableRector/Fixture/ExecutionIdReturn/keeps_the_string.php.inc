<?php

namespace unit\DurableRector\Fixture\ExecutionIdReturn;

use Gplanchat\Durable\Event\ChildWorkflowCompleted;
use Gplanchat\Durable\Event\WorkflowCancellationRequested;
use Gplanchat\Durable\Event\WorkflowContinuedAsNew;
use Gplanchat\Durable\WorkflowEnvironment;

final class ReadsTheIds
{
    public function __invoke(WorkflowEnvironment $env, ChildWorkflowCompleted $child, WorkflowCancellationRequested $cancel, WorkflowContinuedAsNew $next): array
    {
        return [
            'execution' => $env->executionId(),
            'child' => $child->childExecutionId(),
            'parent' => $cancel->sourceParentExecutionId(),
            'next' => $next->newExecutionId(),
        ];
    }
}

?>
-----
<?php

namespace unit\DurableRector\Fixture\ExecutionIdReturn;

use Gplanchat\Durable\Event\ChildWorkflowCompleted;
use Gplanchat\Durable\Event\WorkflowCancellationRequested;
use Gplanchat\Durable\Event\WorkflowContinuedAsNew;
use Gplanchat\Durable\WorkflowEnvironment;

final class ReadsTheIds
{
    public function __invoke(WorkflowEnvironment $env, ChildWorkflowCompleted $child, WorkflowCancellationRequested $cancel, WorkflowContinuedAsNew $next): array
    {
        return [
            'execution' => $env->executionId()->toString(),
            'child' => $child->childExecutionId()->toString(),
            'parent' => $cancel->sourceParentExecutionId()?->toString(),
            'next' => $next->newExecutionId()?->toString(),
        ];
    }
}

?>
