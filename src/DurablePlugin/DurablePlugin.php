<?php

declare(strict_types=1);

namespace Gplanchat\Durable\Plugin;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class DurablePlugin extends Bundle
{
    /** The admin prefix of the routes when the application has no Sylius admin to name one (#520). */
    public const DEFAULT_ADMIN_PATH_NAME = 'admin';

    public function build(ContainerBuilder $container): void
    {
        // The routes live under `%sylius_admin.path_name%`, which only Sylius defines, in its app
        // config. A compiler pass runs once every config file and extension is loaded, so it sees
        // that value when Sylius is there and fills the gap only when it is not.
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if (!$container->hasParameter('sylius_admin.path_name')) {
                    $container->setParameter('sylius_admin.path_name', DurablePlugin::DEFAULT_ADMIN_PATH_NAME);
                }
            }
        });
    }
}
