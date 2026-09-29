<?php

namespace unit\DurableRector\Fixture\ExecutionId;

use Gplanchat\Durable\ExecutionId;
use Gplanchat\Durable\Port\WorkflowRunCatalogInterface;
use Gplanchat\Durable\Store\EventStoreInterface;

final class AlreadyMigrated
{
    public function __invoke(EventStoreInterface $events, WorkflowRunCatalogInterface $catalog, ExecutionId $id, $unknown, object $other): void
    {
        $events->countEventsInStream($id);
        $catalog->findRun($unknown);
        $other->readStream('not-a-port');
    }
}
