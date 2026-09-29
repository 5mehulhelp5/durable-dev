<?php

declare(strict_types=1);

namespace integration\Temporal;

use Gplanchat\Bridge\Temporal\Store\TaskQueuePollers;
use Gplanchat\Bridge\Temporal\Store\TemporalTaskQueueProbe;
use Gplanchat\Bridge\Temporal\TemporalConnection;

/**
 * The server's answer, not a canned one. No "stop the worker, see it gone" case: the server keeps
 * a stopped poller listed for minutes, which would make it slow or flaky.
 */
final class TaskQueuePollersTest extends TemporalServerTestCase
{
    public function testTheQueuesTheWorkersPollShowAPoller(): void
    {
        $probe = new TemporalTaskQueueProbe($this->client, $this->connection);
        $since = new \DateTimeImmutable('-1 minute');

        $deadline = microtime(true) + 20.0;
        do {
            $queues = $probe->describe();
            $polled = array_filter($queues, static fn(TaskQueuePollers $q): bool => $q->pollers > 0 && $q->polledSince($since));
            if (2 === \count($polled)) {
                break;
            }
            usleep(250_000);
        } while (microtime(true) < $deadline);

        foreach ($queues as $queue) {
            self::assertNull($queue->error, $queue->type);
            self::assertGreaterThan(0, $queue->pollers, $queue->type);
            self::assertTrue($queue->polledSince($since), $queue->type);
        }
    }

    public function testAQueueNobodyServesHasNoPoller(): void
    {
        $unserved = new TemporalConnection(
            target: $this->connection->target,
            namespace: $this->connection->namespace,
            workflowTaskQueue: 'durable-it-unserved-' . bin2hex(random_bytes(6)),
            activityTaskQueue: 'durable-it-unserved-' . bin2hex(random_bytes(6)),
            transport: $this->connection->transport,
        );

        foreach ((new TemporalTaskQueueProbe($this->client, $unserved))->describe() as $queue) {
            self::assertNull($queue->error, $queue->type);
            self::assertSame(0, $queue->pollers, $queue->type);
            self::assertFalse($queue->polledSince(new \DateTimeImmutable('-1 minute')), $queue->type);
        }
    }
}
