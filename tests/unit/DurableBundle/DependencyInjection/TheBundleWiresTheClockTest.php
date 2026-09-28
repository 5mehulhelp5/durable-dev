<?php

declare(strict_types=1);

namespace unit\Gplanchat\DurableBundle\DependencyInjection;

use Gplanchat\Durable\Bundle\DependencyInjection\DurableExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The core reads time through FrameworkBundle's `clock` service when the application has one, and
 * through its own system clock otherwise: a missing `clock` resolves to null, which the core
 * replaces with its SystemClock (#617).
 *
 * @internal
 */
final class TheBundleWiresTheClockTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function servicesThatReadTime(): iterable
    {
        foreach (['durable.runtime', 'durable.activity_message_processor', 'durable.activity_transport', 'durable.event_store.inner', 'durable.run_catalog.in_memory', 'durable.uuid_generator'] as $id) {
            yield $id => [$id];
        }
    }

    #[DataProvider('servicesThatReadTime')]
    public function testTheServiceReadsTheApplicationsClockWhenThereIsOne(string $id): void
    {
        $container = new ContainerBuilder();
        (new DurableExtension())->load([['backend' => 'in_memory']], $container);

        $clocks = array_filter(
            $container->getDefinition($id)->getArguments(),
            static fn(mixed $argument): bool => $argument instanceof Reference && 'clock' === (string) $argument,
        );

        self::assertCount(1, $clocks);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, array_values($clocks)[0]->getInvalidBehavior());
    }
}
