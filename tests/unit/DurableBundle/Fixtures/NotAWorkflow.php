<?php

declare(strict_types=1);

namespace unit\DurableBundle\Fixtures;

/**
 * Carries no attribute: must not reach the registry.
 */
final class NotAWorkflow
{
    public function run(): string
    {
        return 'no';
    }
}
