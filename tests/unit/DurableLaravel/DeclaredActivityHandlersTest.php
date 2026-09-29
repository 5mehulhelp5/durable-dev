<?php

declare(strict_types=1);

namespace unit\DurableLaravel;

use Gplanchat\Durable\ActivityExecutor;
use Gplanchat\Durable\Attribute\AsActivity;
use Gplanchat\Durable\Attribute\AsActivityHandler;
use Gplanchat\Durable\Attribute\AsActivityMethod;
use Gplanchat\Durable\Laravel\DurableServiceProvider;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use unit\DurableLaravel\Fixtures\FakeQueue;
use unit\DurableLaravel\Fixtures\FakeQueueFactory;

#[AsActivity('greet')]
interface GreetActivities
{
    #[AsActivityMethod('hello')]
    public function hello(string $who): string;
}

#[AsActivity('count')]
interface CountActivities
{
    #[AsActivityMethod('one')]
    public function one(): int;
}

final class Greeter implements GreetActivities, CountActivities
{
    public static int $built = 0;

    public function __construct()
    {
        ++self::$built;
    }

    public function hello(string $who): string
    {
        return 'hello ' . $who;
    }

    public function one(): int
    {
        return 1;
    }
}

#[AsActivityHandler(GreetActivities::class)]
final class GreeterNamingItsContract implements GreetActivities, CountActivities
{
    public function hello(string $who): string
    {
        return 'named ' . $who;
    }

    public function one(): int
    {
        return 1;
    }
}

/** #713: activity handlers declared in `activity_handlers`, as Symfony and Magento declare theirs. */
final class DeclaredActivityHandlersTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function backends(): iterable
    {
        yield 'memory' => ['memory'];
        yield 'illuminate' => ['illuminate'];
    }

    #[DataProvider('backends')]
    public function testADeclaredHandlerRunsUnderTheNamesItsContractCarries(string $backend): void
    {
        $executor = $this->registered($backend, [Greeter::class])->make(ActivityExecutor::class);

        self::assertSame('hello ada', $executor->execute('greet.hello', ['who' => 'ada']));
        self::assertSame(1, $executor->execute('count.one', []));
    }

    public function testTheHandlerIsBuiltWhenItsActivityRunsNotBefore(): void
    {
        Greeter::$built = 0;
        $executor = $this->registered('memory', [Greeter::class])->make(ActivityExecutor::class);
        self::assertSame(0, Greeter::$built);

        $executor->execute('count.one', []);

        self::assertSame(1, Greeter::$built);
    }

    public function testAHandlerNamingItsContractServesOnlyThatContract(): void
    {
        $executor = $this->registered('memory', [GreeterNamingItsContract::class])->make(ActivityExecutor::class);

        self::assertSame('named ada', $executor->execute('greet.hello', ['who' => 'ada']));
        $this->expectExceptionMessage('No handler registered for activity "count.one"');
        $executor->execute('count.one', []);
    }

    /** @param list<string> $handlers */
    private function registered(string $backend, array $handlers): Container
    {
        $app = new Container();
        $capsule = new Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app->instance(Connection::class, $capsule->getConnection());
        $app->instance(QueueFactory::class, new FakeQueueFactory(new FakeQueue()));
        $app->instance('config', new \ArrayObject(
            ['durable' => ['backend' => $backend, 'activity_handlers' => $handlers]],
            \ArrayObject::ARRAY_AS_PROPS,
        ));
        (new DurableServiceProvider($app))->register();

        return $app;
    }
}
