<?php

namespace unit\DurableRector\Fixture\ExecutionId;

use Gplanchat\Durable\Event\Event;
use Gplanchat\Durable\Store\EventStoreInterface;

// Its parameters become ExecutionId by hand (UPGRADE): a wrap here would turn into
// fromString(ExecutionId) once they do.
final class CountingEventStore implements EventStoreInterface
{
    public function __construct(private EventStoreInterface $inner) {}

    public function append(Event $event): void
    {
        $this->inner->append($event);
    }

    public function readStream(string $executionId): iterable
    {
        return $this->inner->readStream($executionId);
    }

    public function readStreamWithRecordedAt(string $executionId): iterable
    {
        return $this->inner->readStreamWithRecordedAt($executionId);
    }

    public function countEventsInStream(string $executionId): int
    {
        return \count([...$this->readStream($executionId)]);
    }
}
