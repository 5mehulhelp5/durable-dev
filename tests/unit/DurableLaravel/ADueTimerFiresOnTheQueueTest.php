<?php

declare(strict_types=1);

namespace unit\DurableLaravel;

use Gplanchat\Bridge\Illuminate\Queue\ResumeLock;
use Gplanchat\Durable\ExecutionId;
use Gplanchat\Durable\Laravel\DurableServiceProvider;
use Gplanchat\Durable\Laravel\Queue\ResumeDeferral;
use Gplanchat\Durable\Port\WorkflowResumeDispatcher;
use Gplanchat\Durable\Store\WorkflowMetadataStore;
use Illuminate\Cache\ArrayStore;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use PHPUnit\Framework\TestCase;
use unit\Durable\Fixtures\FrozenClock;
use unit\DurableLaravel\Fixtures\FakeQueue;
use unit\DurableLaravel\Fixtures\FakeQueueFactory;
use unit\DurableLaravel\Fixtures\NapWorkflow;

/**
 * #726: on the illuminate backend, a run that sleeps wakes up once its timer is due.
 *
 * Distributed, not inline: every job goes through the queue and a worker loop takes them one at a
 * time, the clock moving by each job's delay as `queue:work` would wait it out. Spike #709 found a
 * copy of the timer dispatch looping on a due timer that never fired; the bound below turns that
 * loop into a failure instead of a hang.
 */
final class ADueTimerFiresOnTheQueueTest extends TestCase
{
    public function testASleepingRunCompletesOnceItsTimerIsDue(): void
    {
        $clock = new FrozenClock();
        $queue = new FakeQueue();
        $app = new Container();
        $capsule = new Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app->instance(Connection::class, $capsule->getConnection());
        $app->instance(QueueFactory::class, new FakeQueueFactory($queue));
        $app->instance('durable.clock', $clock);
        $app->instance('config', new \ArrayObject(
            ['durable' => ['backend' => 'illuminate', 'workflows' => [NapWorkflow::class]]],
            \ArrayObject::ARRAY_AS_PROPS,
        ));
        (new DurableServiceProvider($app))->register();

        $id = ExecutionId::fromString('exec-nap');
        $app->make(WorkflowResumeDispatcher::class)->dispatchNewWorkflowRun($id, NapWorkflow::class, []);

        $lock = new ResumeLock(new ArrayStore());
        for ($jobs = 0; [] !== $queue->pushed && $jobs < 20; ++$jobs) {
            $next = array_shift($queue->pushed);
            $clock->advance((float) ($next['delay'] ?? 0));
            $app->call([$next['job'], 'handle'], ['lock' => $lock, 'queue' => new FakeQueueFactory($queue), 'deferral' => new ResumeDeferral()]);
        }

        self::assertTrue($app->make(WorkflowMetadataStore::class)->get($id)['completed'] ?? false, \sprintf('not completed after %d jobs', $jobs));
    }
}
