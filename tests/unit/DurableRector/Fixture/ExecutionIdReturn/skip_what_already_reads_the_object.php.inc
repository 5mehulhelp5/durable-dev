<?php

namespace unit\DurableRector\Fixture\ExecutionIdReturn;

use Gplanchat\Durable\Event\ChildWorkflowCompleted;
use Gplanchat\Durable\ExecutionId;
use Gplanchat\Durable\WorkflowEnvironment;

final class AlreadyMigrated
{
    public function __invoke(WorkflowEnvironment $env, ChildWorkflowCompleted $child, ExecutionId $other, \SplFileInfo $file): array
    {
        return [
            $env->executionId()->toString(),
            $child->childExecutionId()->equals($other),
            $file->getFilename(),
        ];
    }
}
