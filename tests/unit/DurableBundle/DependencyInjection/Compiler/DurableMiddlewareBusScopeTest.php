<?php

declare(strict_types=1);

namespace unit\Gplanchat\DurableBundle\DependencyInjection\Compiler;

use Gplanchat\Durable\Bundle\DependencyInjection\DurableExtension;
use Gplanchat\Durable\Bundle\DurableBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Which buses the bundle's middlewares install on.
 *
 * They went to the head of **every** bus of the application, with no way out. The DBAL backend's
 * resume lock takes one lock per execution and the profiling middleware measures: applying them to
 * an application's business command bus, which carries no durable message, is work and a point of
 * contention nobody asked for.
 *
 * The defect is not being at the head — a lock must wrap what follows, a `doctrine_transaction`
 * included — nor being set by a pass, for want of a `messenger.middleware` tag in Symfony. It is
 * not being able to say "those only".
 */
final class DurableMiddlewareBusScopeTest extends TestCase
{
    public function testWithoutConfigurationEveryBusIsServedAsBefore(): void
    {
        $container = $this->compile([], ['messenger.bus.commands', 'messenger.bus.durable']);

        self::assertContains('durable.dbal.single_resume_lock', $this->stackOf($container, 'messenger.bus.commands'));
        self::assertContains('durable.dbal.single_resume_lock', $this->stackOf($container, 'messenger.bus.durable'));
    }

    public function testAListRestrictsTheServedBuses(): void
    {
        $container = $this->compile(
            ['messenger' => ['buses' => ['messenger.bus.durable']]],
            ['messenger.bus.commands', 'messenger.bus.durable'],
        );

        self::assertNotContains(
            'durable.dbal.single_resume_lock',
            $this->stackOf($container, 'messenger.bus.commands'),
            'the business command bus carries no durable message: nothing has to install there',
        );
        self::assertContains(
            'durable.dbal.single_resume_lock',
            $this->stackOf($container, 'messenger.bus.durable'),
        );
    }

    /**
     * A list naming a bus that does not exist must not produce silence: that is exactly the
     * mistake one believes fixed while nothing got installed.
     */
    public function testANamedBusThatDoesNotExistIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/messenger\.bus\.typo/');

        $this->compile(
            ['messenger' => ['buses' => ['messenger.bus.typo']]],
            ['messenger.bus.durable'],
        );
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string>         $buses
     */
    private function compile(array $config, array $buses): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        (new DurableExtension())->load([$config + ['event_store' => ['type' => 'dbal']]], $container);

        // What FrameworkExtension sets: a declared bus, and its stack in a parameter.
        foreach ($buses as $busId) {
            $container->register($busId)->addTag('messenger.bus');
            $container->setParameter($busId . '.middleware', [['id' => 'send_message']]);
        }
        // And what `framework.lock` sets, which the DBAL backend needs.
        $container->register('lock.factory', \stdClass::class);

        (new DurableBundle())->build($container);
        foreach ($container->getCompilerPassConfig()->getBeforeOptimizationPasses() as $pass) {
            $pass->process($container);
        }

        return $container;
    }

    /**
     * @return list<string>
     */
    private function stackOf(ContainerBuilder $container, string $busId): array
    {
        /** @var list<array{id?: string}> $stack */
        $stack = $container->getParameter($busId . '.middleware');

        return array_values(array_filter(array_column($stack, 'id')));
    }
}
