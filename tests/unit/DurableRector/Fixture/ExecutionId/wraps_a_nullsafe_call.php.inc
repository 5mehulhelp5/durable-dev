<?php

namespace unit\DurableRector\Fixture\ExecutionId;

use Gplanchat\Durable\Port\WorkflowResumeDispatcher;

final class MaybeResumes
{
    public function __construct(private ?WorkflowResumeDispatcher $dispatcher) {}

    public function __invoke(string $executionId): void
    {
        $this->dispatcher?->dispatchResume($executionId);
    }
}

?>
-----
<?php

namespace unit\DurableRector\Fixture\ExecutionId;

use Gplanchat\Durable\Port\WorkflowResumeDispatcher;

final class MaybeResumes
{
    public function __construct(private ?WorkflowResumeDispatcher $dispatcher) {}

    public function __invoke(string $executionId): void
    {
        $this->dispatcher?->dispatchResume(\Gplanchat\Durable\ExecutionId::fromString($executionId));
    }
}

?>
