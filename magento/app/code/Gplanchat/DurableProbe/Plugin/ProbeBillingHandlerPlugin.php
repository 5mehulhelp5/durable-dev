<?php

declare(strict_types=1);

namespace Gplanchat\DurableProbe\Plugin;

use Gplanchat\DurableProbe\Nexus\ProbeBillingHandler;

/**
 * A plugin on the bench's Nexus handler, there only to make Magento generate
 * `ProbeBillingHandler\Interceptor` (#766): the runtime then receives the Interceptor, which does
 * not carry `#[AsNexusServiceHandler]`, and must read it from the class it intercepts.
 *
 * It changes nothing: the answer passes through as the handler gave it.
 */
class ProbeBillingHandlerPlugin
{
    /**
     * @param array{accepted: bool, reason: ?string} $result
     *
     * @return array{accepted: bool, reason: ?string}
     */
    public function afterVerify(ProbeBillingHandler $subject, array $result): array
    {
        return $result;
    }
}
