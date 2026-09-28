<?php

declare(strict_types=1);

namespace Gplanchat\Durable\Plugin\DependencyInjection;

use Gplanchat\Durable\Observation\RunDashboard;
use Gplanchat\Durable\Plugin\Grid\RunGridViews;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class DurablePluginExtension extends Extension implements PrependExtensionInterface
{
    /** {@see \Gplanchat\Durable\Plugin\Grid\SyliusRunGrid::GRID}, which this class cannot name. */
    private const RUN_GRID = 'gplanchat_durable_runs';
    private const RUN_GRID_PROVIDER = 'Gplanchat\\Durable\\Plugin\\Grid\\SyliusRunGrid';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../config'));
        $loader->load('services.php');

        // The run list as a Sylius grid (#383), where the grid bundle is registered: its classes
        // can be installed without it, and then its services are not. load() runs on a container
        // that holds only this extension, so the kernel's bundle list is what says so. The class is
        // named by string, since the root analysis does not see grid-bundle.
        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];
        if (\is_array($bundles) && isset($bundles['SyliusGridBundle'])) {
            $container->register(self::RUN_GRID_PROVIDER, self::RUN_GRID_PROVIDER)
                ->setArguments([new Reference(RunDashboard::class), new Reference('sylius.grid.provider'), new Reference('sylius.grid.view_factory')])
                ->addTag('sylius.grid_data_provider');
            $container->setAlias(RunGridViews::class, self::RUN_GRID_PROVIDER);
        }
    }

    /**
     * The dashboard's place in the Sylius admin hooks (#383), declared only when Sylius's
     * TwigHooks is there: the plugin does not require Sylius (owner decision, 2026-09-24).
     */
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('sylius_grid')) {
            $container->prependExtensionConfig('sylius_grid', ['grids' => [self::RUN_GRID => self::runGrid()]]);
        }

        if (!$container->hasExtension('sylius_twig_hooks')) {
            return;
        }

        // With grid-bundle, the list is the Sylius grid (#383, slice B), under the counters; without
        // it, the dashboard keeps its own list.
        $grid = $container->hasExtension('sylius_grid');
        $container->prependExtensionConfig('sylius_twig_hooks', ['hooks' => [
            'sylius_admin.durable_dashboard.index.content' => ($grid ? [] : ['grid' => ['enabled' => false]]) + [
                // The dashboard carries its own heading, translated in the `durable` domain.
                'header' => ['enabled' => false],
                'dashboard' => [
                    'template' => '@DurablePlugin/admin/dashboard/index/content/dashboard.html.twig',
                    'priority' => 150,
                ],
            ],
            // The admin's table and "no results" read a Pagerfanta, and the catalog pages by cursor:
            // the plugin's own, for this grid only, never under `sylius_admin.common`.
            'sylius_admin.durable_dashboard.index.content.grid' => [
                'filters' => ['template' => '@DurablePlugin/admin/grid/filters.html.twig'],
                'data_table' => ['template' => '@DurablePlugin/admin/grid/data_table.html.twig'],
                'no_data_block' => ['enabled' => false],
            ],
        ]]);
    }

    /**
     * The run list as a grid (#383, slice B). Its data provider reads the run catalog and pages by
     * its cursor, so no field sorts and no limit is offered: the catalog decides both. The filters
     * are the page's own form, offered only where the catalog can apply them.
     *
     * @return array<string, mixed>
     */
    private static function runGrid(): array
    {
        $template = static fn(string $field, string $path, string $label): array => [
            'type' => 'twig',
            'path' => $path,
            'label' => $label,
            'options' => ['template' => '@DurablePlugin/admin/grid/field/' . $field . '.html.twig'],
        ];

        return [
            'provider' => self::RUN_GRID_PROVIDER,
            'fields' => [
                'execution' => $template('execution', '[row]', 'durable.grid.execution'),
                'workflow' => ['type' => 'string', 'path' => '[workflowName]', 'label' => 'durable.grid.workflow'],
                'status' => $template('status', '[status]', 'durable.grid.status'),
                'startedAt' => $template('date', '[startedAt]', 'durable.grid.started_at'),
                'notes' => $template('notes', '[row]', 'durable.grid.notes'),
            ],
        ];
    }
}
