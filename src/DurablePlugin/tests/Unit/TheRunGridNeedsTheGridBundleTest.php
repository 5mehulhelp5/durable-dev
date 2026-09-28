<?php

declare(strict_types=1);

namespace Gplanchat\Durable\Plugin\Tests\Unit;

use Gplanchat\Durable\Plugin\DependencyInjection\DurablePluginExtension;
use Gplanchat\Durable\Plugin\Grid\RunGridViews;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The run grid's services reference the grid bundle's own. Its classes can be installed without the
 * bundle being registered (#383, the Symfony matrix found it): the grid is wired only when the
 * kernel registers SyliusGridBundle.
 */
final class TheRunGridNeedsTheGridBundleTest extends TestCase
{
    public function testWithoutTheGridBundleNoGridServiceIsDeclared(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['FrameworkBundle' => 'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle']);
        (new DurablePluginExtension())->load([], $container);

        self::assertFalse($container->has(RunGridViews::class));
        self::assertFalse($container->hasDefinition('Gplanchat\\Durable\\Plugin\\Grid\\SyliusRunGrid'));
    }

    public function testWithTheGridBundleTheGridIsTheListsDataProvider(): void
    {
        // What the kernel sets, and all that load() can see: it runs on a container that holds no
        // other extension.
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SyliusGridBundle' => 'Sylius\\Bundle\\GridBundle\\SyliusGridBundle']);
        (new DurablePluginExtension())->load([], $container);

        self::assertTrue($container->has(RunGridViews::class));
        self::assertArrayHasKey('sylius.grid_data_provider', $container->getDefinition('Gplanchat\\Durable\\Plugin\\Grid\\SyliusRunGrid')->getTags());
    }
}
