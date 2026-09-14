<?php

declare(strict_types=1);

namespace unit\Gplanchat\DurableBundle\DependencyInjection\Compiler;

use Gplanchat\Durable\ActivityExecutor;
use Gplanchat\Durable\Bundle\DependencyInjection\DurableExtension;
use Gplanchat\Durable\Bundle\DurableBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use unit\DurableBundle\Fixtures\FirstHandler;
use unit\DurableBundle\Fixtures\InstanceCounter;
use unit\DurableBundle\Fixtures\SecondHandler;

/**
 * Executing an activity must build only its handler.
 *
 * The executor received its handlers as `[Reference, '__invoke']` callables. To build that array,
 * the container must resolve every reference: it therefore instantiates **all** the handlers of
 * the application — and their dependencies, connections and HTTP clients included — to call a
 * single one.
 *
 * What that costs does not show in development, where handlers are light. It shows on a worker
 * that handles one activity per message, with twenty declared contracts.
 */
final class ActivityHandlersAreLazyTest extends TestCase
{
    protected function setUp(): void
    {
        InstanceCounter::reset();
    }

    public function testASingleExecutedActivityBuildsOnlyItsHandler(): void
    {
        $container = $this->compile();

        /** @var ActivityExecutor $executor */
        $executor = $container->get(ActivityExecutor::class);

        self::assertSame(
            0,
            InstanceCounter::total(),
            'getting the executor must build no handler',
        );

        $executor->execute('first.perform', ['what' => 'this']);

        self::assertSame(['first'], InstanceCounter::built());
    }

    public function testBothHandlersStayReachable(): void
    {
        $container = $this->compile();

        /** @var ActivityExecutor $executor */
        $executor = $container->get(ActivityExecutor::class);

        self::assertSame('first:this', $executor->execute('first.perform', ['what' => 'this']));
        self::assertSame('second:that', $executor->execute('second.perform', ['what' => 'that']));
        self::assertSame(['first', 'second'], InstanceCounter::built());
    }

    public function testAnUnknownActivityStillFailsClearly(): void
    {
        $container = $this->compile();

        /** @var ActivityExecutor $executor */
        $executor = $container->get(ActivityExecutor::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/unknown/');

        $executor->execute('unknown', []);
    }

    private function compile(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        (new DurableExtension())->load([[]], $container);
        $container->register('messenger.default_bus', \stdClass::class)->setPublic(true);

        foreach ([FirstHandler::class, SecondHandler::class] as $class) {
            $container->register($class, $class)->setAutoconfigured(true)->setPublic(false);
        }


        (new DurableBundle())->build($container);
        $container->compile();

        return $container;
    }
}
