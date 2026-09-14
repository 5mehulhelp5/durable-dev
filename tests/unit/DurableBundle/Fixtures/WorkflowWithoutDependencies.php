<?php

declare(strict_types=1);

namespace unit\DurableBundle\Fixtures;

use Gplanchat\Durable\Attribute\AsWorkflow;
use Gplanchat\Durable\Attribute\AsWorkflowMethod;

/**
 * A workflow with no dependency: nothing to autowire, hence nothing that could trap.
 */
#[AsWorkflow('WithoutDependencies')]
final class WorkflowWithoutDependencies
{
    #[AsWorkflowMethod]
    public function run(): string
    {
        return 'ok';
    }
}
