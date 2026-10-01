<?php

namespace unit\DurableRector\Fixture\Unmigratable;

use Temporal\Exception\Failure\TimeoutFailure;

class AlreadyMarkedHandler
{
    // durable-rector: TimeoutFailure has no Durable counterpart — a catch on it never matches after migration; decide by hand
    public function handle(TimeoutFailure $failure): void
    {
    }
}

?>
