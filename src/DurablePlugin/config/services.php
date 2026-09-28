<?php

declare(strict_types=1);

use Gplanchat\Durable\Observation\PayloadRedactorInterface;
use Gplanchat\Durable\Observation\RunDashboard;
use Gplanchat\Durable\Plugin\Controller\AdminDashboardController;
use Gplanchat\Durable\Plugin\EventListener\AdminMenuListener;
use Gplanchat\Durable\Plugin\Grid\RunGridViews;
use Gplanchat\Durable\Port\WorkflowRunCatalogInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The catalog is absent when no backend is readable: the bundle then registers none, and the
    // page must say so rather than failing to wire.
    $services
        ->set(RunDashboard::class)
        ->arg('$catalog', service(WorkflowRunCatalogInterface::class)->nullOnInvalid())
        // The redactor the profiler and diagnose use, the application's if it aliased its own.
        ->arg('$redactor', service(PayloadRedactorInterface::class)->nullOnInvalid())
    ;

    // The run list as a Sylius grid (#383), where grid-bundle is installed; the list page keeps its
    // own list otherwise. The grid finds its data provider by this service id.
    if (interface_exists('Sylius\\Component\\Grid\\Data\\DataProviderInterface')) {
        // Named by string: the root analysis does not see grid-bundle, so the class stays unnamed
        // outside its own file.
        $grid = 'Gplanchat\\Durable\\Plugin\\Grid\\SyliusRunGrid';
        $services
            ->set($grid)
            ->arg('$dashboard', service(RunDashboard::class))
            ->arg('$grids', service('sylius.grid.provider'))
            ->arg('$views', service('sylius.grid.view_factory'))
            ->tag('sylius.grid_data_provider')
        ;
        $services->alias(RunGridViews::class, $grid);
    }

    $services
        ->set(AdminDashboardController::class)
        ->autowire()
        ->tag('controller.service_arguments')
    ;

    // Sylius admin menu entry (safe no-op if event payload is unexpected).
    $services
        ->set(AdminMenuListener::class)
        ->autowire()
        ->tag('kernel.event_listener', [
            'event' => 'sylius.menu.admin.main',
            'method' => 'addDashboardItem',
        ])
    ;
};
