<?php

namespace unit\DurableRector\Fixture\ExecutionId;

use Gplanchat\Durable\Port\WorkflowResumeDispatcher;
use Gplanchat\Durable\Store\WorkflowMetadataStore;

final class Starter
{
    public function __invoke(WorkflowMetadataStore $metadata, WorkflowResumeDispatcher $dispatcher): void
    {
        $metadata->save('order-1', 'Order', []);
        $dispatcher->dispatchNewWorkflowRun('order-1', 'Order', []);
    }
}

?>
-----
<?php

namespace unit\DurableRector\Fixture\ExecutionId;

use Gplanchat\Durable\Port\WorkflowResumeDispatcher;
use Gplanchat\Durable\Store\WorkflowMetadataStore;

final class Starter
{
    public function __invoke(WorkflowMetadataStore $metadata, WorkflowResumeDispatcher $dispatcher): void
    {
        $metadata->save(\Gplanchat\Durable\ExecutionId::fromString('order-1'), 'Order', []);
        $dispatcher->dispatchNewWorkflowRun(\Gplanchat\Durable\ExecutionId::fromString('order-1'), 'Order', []);
    }
}

?>
