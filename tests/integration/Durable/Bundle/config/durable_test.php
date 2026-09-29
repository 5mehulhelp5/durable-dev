<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $container->import('test_null_logger.php');
    $container->extension('framework', [
        'secret' => 'test',
        'http_method_override' => false,
        'test' => true,
    ]);
    $container->extension('durable', []);

    // The bundle's ids are private since #342, and an id nothing injects is removed at compile.
    // The bundle itself injects neither of these two; an application does, as this service does.
    $container->services()->set('app.durable_consumer', \ArrayObject::class)
        ->args([[service(\Gplanchat\Durable\Port\WorkflowBackendInterface::class), service(\Gplanchat\Durable\Query\WorkflowQueryRunner::class)]])
        ->public();
};
