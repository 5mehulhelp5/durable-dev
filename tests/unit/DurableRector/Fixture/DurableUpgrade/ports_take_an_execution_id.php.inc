<?php

namespace unit\DurableRector\Fixture\DurableUpgrade;

use Gplanchat\Durable\Store\EventStoreInterface;

final class ReadsAJournal
{
    public function __invoke(EventStoreInterface $events, string $executionId): void
    {
        $events->readStream($executionId);
    }
}

?>
-----
<?php

namespace unit\DurableRector\Fixture\DurableUpgrade;

use Gplanchat\Durable\Store\EventStoreInterface;

final class ReadsAJournal
{
    public function __invoke(EventStoreInterface $events, string $executionId): void
    {
        $events->readStream(\Gplanchat\Durable\ExecutionId::fromString($executionId));
    }
}

?>
