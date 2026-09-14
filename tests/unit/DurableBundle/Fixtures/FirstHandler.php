<?php

declare(strict_types=1);

namespace unit\DurableBundle\Fixtures;

use Gplanchat\Durable\Attribute\AsActivityHandler;

#[AsActivityHandler(contract: FirstContract::class)]
final class FirstHandler implements FirstContract
{
    public function __construct()
    {
        InstanceCounter::note('first');
    }

    public function perform(string $what): string
    {
        return 'first:' . $what;
    }
}
