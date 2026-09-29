<?php

namespace unit\DurableRector\Fixture\ExecutionId;

use Gplanchat\Durable\Store\EventStoreInterface;

final class LeftAlone
{
    /**
     * @param list<string> $ids
     */
    public function __invoke(EventStoreInterface $events, string $executionId, array $ids, ?string $maybe): void
    {
        $events->readStream(executionId: $executionId);
        $events->readStream(...$ids);
        $events->readStream($maybe);
    }
}
