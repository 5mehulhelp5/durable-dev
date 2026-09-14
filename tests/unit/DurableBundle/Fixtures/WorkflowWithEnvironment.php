<?php

declare(strict_types=1);

namespace unit\DurableBundle\Fixtures;

use Gplanchat\Durable\Attribute\AsWorkflow;
use Gplanchat\Durable\Attribute\AsWorkflowMethod;
use Gplanchat\Durable\WorkflowEnvironment;

/**
 * The shape the getting-started guide teaches: a constructor that receives the environment, which
 * is **not** a container service. This is the shape issue #255 announces as a trap as soon as the
 * attribute is autoconfigured.
 */
#[AsWorkflow('WithEnvironment')]
final class WorkflowWithEnvironment
{
    public function __construct(private readonly WorkflowEnvironment $environment) {}

    #[AsWorkflowMethod]
    public function run(string $what): string
    {
        return $what;
    }
}
