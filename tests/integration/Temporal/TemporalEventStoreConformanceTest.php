<?php

declare(strict_types=1);

namespace integration\Temporal;

use Gplanchat\Bridge\Temporal\Grpc\TemporalHistoryCursor;
use Gplanchat\Bridge\Temporal\Grpc\WorkflowServiceExecutionRpc;
use Gplanchat\Bridge\Temporal\Store\TemporalReadThroughEventStore;
use Gplanchat\Bridge\Temporal\WorkflowClient;
use Gplanchat\Bridge\Temporal\WorkflowServiceClientFactory;
use Gplanchat\Durable\Store\EventStoreInterface;
use Gplanchat\Durable\Store\InMemoryEventStore;
use Gplanchat\Durable\Testing\EventStoreConformanceTestCase;

/**
 * DUR041's port tier against `TemporalReadThroughEventStore`, the only Temporal
 * `EventStoreInterface` adapter, on a real server (#326).
 *
 * What the server can prove here is bounded by the adapter's contract. Temporal's history is
 * written by the workflow worker, never through `append()`: the adapter appends to its local store,
 * and reads that store whenever it holds the stream. So the round-trip, order, restart and count
 * cases prove the local-first delegation, and only the empty-stream paths reach the server
 * (`testAnUnknownExecutionIsEmptyRatherThanAnError`, and the first count of
 * `testCountingAgreesWithTheStreamLength`): an execution the server does not know must read back
 * empty, not raise.
 *
 * The replay tier cannot apply as {@see \Gplanchat\Durable\Testing\EventStoreReplayConformanceTestCase}
 * runs it: its runner appends inline, so every event would land in the local store and Temporal
 * would never be read. The read path, DUR029's conversion, is instead checked in
 * `TemporalHistoryReplayConformanceTest`, which runs the conformance workflow on the server and
 * reads its journal back through this adapter. Replay cases added to the shared tier later do not
 * reach that path by themselves.
 *
 * @see DUR041
 * @see DUR029
 */
final class TemporalEventStoreConformanceTest extends EventStoreConformanceTestCase
{
    use FreshNamespace;

    private TemporalReadThroughEventStore $store;

    protected function setUp(): void
    {
        $connection = self::freshNamespaceConnection();
        $client = WorkflowServiceClientFactory::create($connection);
        $cursor = new TemporalHistoryCursor($client, $connection);

        $this->store = new TemporalReadThroughEventStore(
            new InMemoryEventStore(),
            $cursor,
            new WorkflowClient($client, $connection, $cursor, new WorkflowServiceExecutionRpc($client)),
        );
    }

    protected function createEventStore(): EventStoreInterface
    {
        return $this->store;
    }
}
