<?php

declare(strict_types=1);

namespace unit\Gplanchat\DurableBundle\DependencyInjection;

use Gplanchat\Durable\Bundle\DataCollector\DurableDataCollector;
use Gplanchat\Durable\Bundle\DependencyInjection\DurableExtension;
use Gplanchat\Durable\Port\WorkflowResumeDispatcher;
use Gplanchat\Durable\Port\WorkflowRunCatalogInterface;
use Gplanchat\Durable\Store\EventStoreInterface;
use Gplanchat\Durable\Store\WorkflowMetadataStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * What the bundle exposes, and what it keeps to itself.
 *
 * A public service is an entry point the compiler can no longer remove or inline, and an implicit
 * compatibility promise: someone will end up calling it. The concrete implementations behind an
 * alias — the DBAL journal, the Temporal catalog — and the projection decorators have no reason
 * to be pulled from the container: they are reached through their interface, which stays public
 * and autowirable.
 */
final class DurableContainerSurfaceTest extends TestCase
{
    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function internalsThatNeedNotBePublic(): iterable
    {
        $dbal = ['event_store' => ['type' => 'dbal'], 'workflow_metadata' => ['type' => 'dbal']];

        yield 'DBAL journal' => [$dbal, 'durable.event_store.dbal'];
        yield 'projected DBAL journal' => [$dbal, 'durable.event_store.dbal.projecting'];
        yield 'DBAL metadata, inner service' => [$dbal, 'durable.workflow_metadata_store.inner'];
        yield 'projected DBAL metadata' => [$dbal, 'durable.workflow_metadata_store.projecting'];
        yield 'DBAL catalog' => [$dbal, 'durable.run_catalog.dbal'];
        yield 'projected in-memory journal' => [[], 'durable.event_store.in_memory.projecting'];
        yield 'in-memory metadata, inner service' => [[], 'durable.workflow_metadata_store.inner'];
        yield 'DBAL journal, in-memory metadata, inner service' => [['event_store' => ['type' => 'dbal']], 'durable.workflow_metadata_store.inner'];
        yield 'projected in-memory metadata' => [[], 'durable.workflow_metadata_store.in_memory.projecting'];
        yield 'in-memory catalog' => [[], 'durable.run_catalog.in_memory'];
        yield 'Temporal workflow task processor' => [['backend' => 'temporal', 'temporal' => ['dsn' => 'temporal://127.0.0.1:7233?namespace=default&tls=0']], \Gplanchat\Bridge\Temporal\Worker\WorkflowTaskProcessor::class];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('internalsThatNeedNotBePublic')]
    public function testAnInternalIsNotPublic(array $config, string $serviceId): void
    {
        $container = $this->load($config);

        self::assertTrue($container->hasDefinition($serviceId), $serviceId . ' must exist for this backend');
        self::assertFalse(
            $container->getDefinition($serviceId)->isPublic(),
            $serviceId . ' has no reason to be pulled from the container: it is reached through its interface',
        );
    }

    /**
     * Each backend × each id: the three are aliases on every backend (#342).
     *
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function publicSurfaceThatMustStaySo(): iterable
    {
        $backends = [
            'in_memory' => [],
            'dbal, legacy keys' => ['event_store' => ['type' => 'dbal'], 'workflow_metadata' => ['type' => 'dbal']],
            'dbal' => ['backend' => 'dbal'],
            'temporal' => ['backend' => 'temporal', 'temporal' => ['dsn' => 'temporal://127.0.0.1:7233?namespace=default&tls=0']],
        ];
        foreach ($backends as $backend => $config) {
            yield $backend . ': the journal' => [$config, EventStoreInterface::class];
            yield $backend . ': the metadata' => [$config, WorkflowMetadataStore::class];
            yield $backend . ': the run catalog' => [$config, WorkflowRunCatalogInterface::class];
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('publicSurfaceThatMustStaySo')]
    public function testThePublicSurfaceStaysReachable(array $config, string $alias): void
    {
        $container = $this->load($config);

        self::assertTrue($container->hasAlias($alias), $alias . ' must stay an alias of the container');
        self::assertTrue(
            $container->getAlias($alias)->isPublic(),
            $alias . ' is what the shipped test trait resolves, and what the documentation names',
        );
    }

    /**
     * Every backend, the profiler on and off.
     *
     * @return iterable<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function everyBackend(): iterable
    {
        $dsn = 'temporal://127.0.0.1:7233?namespace=default&tls=0';
        $backends = [
            'in_memory' => ['backend' => 'in_memory'],
            'in_memory, Messenger activity transport' => ['backend' => 'in_memory', 'activity_transport' => ['type' => 'messenger']],
            'dbal, Messenger activity transport' => ['backend' => 'dbal', 'activity_transport' => ['type' => 'messenger']],
            'dbal' => ['backend' => 'dbal'],
            'dbal, legacy keys' => ['event_store' => ['type' => 'dbal'], 'workflow_metadata' => ['type' => 'dbal']],
            'dbal journal, in-memory metadata' => ['event_store' => ['type' => 'dbal']],
            'temporal' => ['backend' => 'temporal', 'temporal' => ['dsn' => $dsn]],
            'dbal with a Temporal DSN' => ['backend' => 'dbal', 'temporal' => ['dsn' => $dsn]],
        ];
        foreach ($backends as $backend => $config) {
            yield $backend . ', profiler on' => [$config, true];
            yield $backend . ', profiler off' => [$config, false];
        }
    }

    /**
     * The public surface is what the user documentation names and what the shipped
     * `DurableBundleTestTrait` fetches, nothing more (#342, decided 2026-09-29). Everything else is
     * reached by autowiring its interface or class, which stays an alias.
     *
     * @param array<string, mixed> $config
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('everyBackend')]
    public function testOnlyTheDocumentedSurfaceIsPublic(array $config, bool $profiler): void
    {
        $container = $this->load($config, $profiler);

        $public = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isPublic() && 'service_container' !== $id) {
                $public[] = $id;
            }
        }
        foreach ($container->getAliases() as $id => $alias) {
            if ($alias->isPublic()) {
                $public[] = $id;
            }
        }
        sort($public);

        $expected = [
            EventStoreInterface::class,
            WorkflowRunCatalogInterface::class,
            WorkflowResumeDispatcher::class,
            WorkflowMetadataStore::class,
        ];
        if ($profiler) {
            $expected[] = DurableDataCollector::class;
        }
        sort($expected);

        self::assertSame($expected, $public);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, bool $debug = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', $debug);
        (new DurableExtension())->load([$config], $container);

        return $container;
    }
}
