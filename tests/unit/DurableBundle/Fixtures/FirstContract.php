<?php

declare(strict_types=1);

namespace unit\DurableBundle\Fixtures;

use Gplanchat\Durable\Attribute\AsActivity;
use Gplanchat\Durable\Attribute\AsActivityMethod;

#[AsActivity(name: 'first')]
interface FirstContract
{
    #[AsActivityMethod(name: 'perform')]
    public function perform(string $what): string;
}
