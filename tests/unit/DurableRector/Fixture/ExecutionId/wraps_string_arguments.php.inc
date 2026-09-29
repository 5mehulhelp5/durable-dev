<?php

namespace unit\DurableRector\Fixture\ExecutionId;

use Gplanchat\Bridge\Temporal\WorkflowClientInterface;
use Gplanchat\Durable\Store\ChildWorkflowParentLinkStoreInterface;
use Gplanchat\Durable\Store\EventStoreInterface;

final class Caller
{
    public function __invoke(
        EventStoreInterface $events,
        ChildWorkflowParentLinkStoreInterface $links,
        WorkflowClientInterface $client,
        string $executionId,
    ): void {
        $events->readStream($executionId);
        $links->link($executionId, 'parent-1');
        $client->startAsync('Order', [], $executionId);
        $client->signal($executionId, 'go');
    }
}

?>
-----
<?php

namespace unit\DurableRector\Fixture\ExecutionId;

use Gplanchat\Bridge\Temporal\WorkflowClientInterface;
use Gplanchat\Durable\Store\ChildWorkflowParentLinkStoreInterface;
use Gplanchat\Durable\Store\EventStoreInterface;

final class Caller
{
    public function __invoke(
        EventStoreInterface $events,
        ChildWorkflowParentLinkStoreInterface $links,
        WorkflowClientInterface $client,
        string $executionId,
    ): void {
        $events->readStream(\Gplanchat\Durable\ExecutionId::fromString($executionId));
        $links->link(\Gplanchat\Durable\ExecutionId::fromString($executionId), \Gplanchat\Durable\ExecutionId::fromString('parent-1'));
        $client->startAsync('Order', [], \Gplanchat\Durable\ExecutionId::fromString($executionId));
        $client->signal($executionId, 'go');
    }
}

?>
