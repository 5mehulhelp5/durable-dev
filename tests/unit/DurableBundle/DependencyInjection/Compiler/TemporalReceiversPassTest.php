<?php

declare(strict_types=1);

namespace unit\Gplanchat\DurableBundle\DependencyInjection\Compiler;

use Gplanchat\Durable\Bundle\DependencyInjection\Compiler\TemporalReceiversPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Under Temporal the bundle owns the worker names. A transport of the same name in messenger.yaml
 * would silently win or lose against it depending on registration order, so the container refuses.
 */
#[CoversClass(TemporalReceiversPass::class)]
final class TemporalReceiversPassTest extends TestCase
{
    public function testATransportThatTakesTheNameOfATemporalWorkerIsRefused(): void
    {
        $container = $this->containerWithWorkers();
        $container->register('messenger.transport.durable_workflows', \stdClass::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/"durable_workflows".*remove it from framework\.messenger\.transports/');

        (new TemporalReceiversPass())->process($container);
    }

    public function testAnUntaggedNexusWorkerLeavesTheNameFree(): void
    {
        // No handler, no durable_nexus worker: the name is not the bundle's to claim.
        $container = $this->containerWithWorkers();
        $container->register('durable.temporal.nexus_receiver', \stdClass::class);
        $container->register('messenger.transport.durable_nexus', \stdClass::class);

        (new TemporalReceiversPass())->process($container);

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function formerTransports(): iterable
    {
        yield 'journal' => ['durable_temporal_journal'];
        yield 'activity' => ['durable_temporal_activity'];
        yield 'nexus' => ['durable_temporal_nexus'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('formerTransports')]
    public function testAFormerTemporalTransportIsRefusedWithItsReplacement(string $former): void
    {
        $container = $this->containerWithWorkers();
        $container->register('messenger.transport.' . $former, \stdClass::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/"' . $former . '".*durable_(workflows|activities|nexus)/');

        (new TemporalReceiversPass())->process($container);
    }

    public function testWithoutTemporalNothingIsChecked(): void
    {
        $container = new ContainerBuilder();
        $container->register('messenger.transport.durable_workflows', \stdClass::class);

        (new TemporalReceiversPass())->process($container);

        $this->addToAssertionCount(1);
    }

    private function containerWithWorkers(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('durable.temporal.workflows_receiver', \stdClass::class)
            ->addTag('messenger.receiver', ['alias' => 'durable_workflows']);
        $container->register('durable.temporal.activities_receiver', \stdClass::class)
            ->addTag('messenger.receiver', ['alias' => 'durable_activities']);

        return $container;
    }
}
