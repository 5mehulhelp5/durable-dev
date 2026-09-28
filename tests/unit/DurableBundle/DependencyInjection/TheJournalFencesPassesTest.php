<?php

declare(strict_types=1);

namespace unit\Gplanchat\DurableBundle\DependencyInjection;

use Gplanchat\Durable\Bundle\DependencyInjection\DurableExtension;
use Gplanchat\Durable\Store\EventStoreInterface;
use Gplanchat\Durable\Store\FencedEventStoreInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * #505, DUR053: on a DBAL journal, every journal the bundle registers must fence passes. One
 * wrapper that implements only EventStoreInterface would hand the engine an unfenced store.
 */
final class TheJournalFencesPassesTest extends TestCase
{
    public function testEveryJournalOfTheDbalBackendFencesPasses(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        (new DurableExtension())->load([['backend' => 'dbal']], $container);

        $journals = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();
            if (null !== $class && is_a($class, EventStoreInterface::class, true)) {
                $journals[$id] = is_a($class, FencedEventStoreInterface::class, true);
            }
        }

        self::assertNotEmpty($journals);
        self::assertSame(array_fill_keys(array_keys($journals), true), $journals);
    }
}
