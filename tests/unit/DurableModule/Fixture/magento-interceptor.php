<?php

declare(strict_types=1);

/*
 * What `bin/magento setup:di:compile` (or developer mode, on the fly) writes under
 * `generated/code/<Class>/Interceptor.php` when a plugin targets a class: a subclass named
 * `<Class>\Interceptor` implementing InterceptorInterface, which carries none of its parent's
 * attributes (#766). Magento is not installed in the root suite; the interface is declared only
 * when absent, so a Magento checkout keeps its own.
 */

namespace Magento\Framework\Interception;

if (!interface_exists(InterceptorInterface::class)) {
    interface InterceptorInterface
    {
        /**
         * @param string               $method
         * @param array<mixed>         $arguments
         * @param array<string, mixed> $pluginInfo
         *
         * @return mixed
         */
        public function ___callPlugins($method, array $arguments, array $pluginInfo);
    }
}

namespace unit\DurableModule\Fixture\NexusBillingHandler;

use Magento\Framework\Interception\InterceptorInterface;

class Interceptor extends \unit\DurableModule\Fixture\NexusBillingHandler implements InterceptorInterface
{
    public function ___callPlugins($method, array $arguments, array $pluginInfo)
    {
        return null;
    }
}

namespace unit\DurableModule\Fixture\NarrowedOrderActivities;

use Magento\Framework\Interception\InterceptorInterface;

class Interceptor extends \unit\DurableModule\Fixture\NarrowedOrderActivities implements InterceptorInterface
{
    public function ___callPlugins($method, array $arguments, array $pluginInfo)
    {
        return null;
    }
}
