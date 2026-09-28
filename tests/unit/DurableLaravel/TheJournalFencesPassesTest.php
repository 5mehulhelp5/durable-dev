<?php

declare(strict_types=1);

namespace unit\DurableLaravel;

use Gplanchat\Durable\Laravel\DurableServiceProvider;
use Gplanchat\Durable\Store\EventStoreInterface;
use Gplanchat\Durable\Store\FencedEventStoreInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use PHPUnit\Framework\TestCase;
use unit\DurableLaravel\Fixtures\FakeQueue;
use unit\DurableLaravel\Fixtures\FakeQueueFactory;

/**
 * #505, DUR053: the journal the provider binds must fence passes, or the engine opens them
 * unfenced and a superseded pass writes again, with every store test green.
 */
final class TheJournalFencesPassesTest extends TestCase
{
    public function testTheIlluminateJournalTheEngineWritesToFencesPasses(): void
    {
        $app = new Container();
        $capsule = new Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app->instance(Connection::class, $capsule->getConnection());
        $app->instance(QueueFactory::class, new FakeQueueFactory(new FakeQueue()));
        $app->instance('config', new \ArrayObject(['durable' => ['backend' => 'illuminate']], \ArrayObject::ARRAY_AS_PROPS));
        (new DurableServiceProvider($app))->register();

        self::assertInstanceOf(FencedEventStoreInterface::class, $app->make(EventStoreInterface::class));
    }
}
