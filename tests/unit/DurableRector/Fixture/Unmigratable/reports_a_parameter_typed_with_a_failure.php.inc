<?php

namespace unit\DurableRector\Fixture\Unmigratable;

use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\TerminatedFailure;

class FailureHandler
{
    public function handle(ApplicationFailure $failure): void
    {
    }
}

function report(?TerminatedFailure $failure, \Temporal\Exception\Failure\ServerFailure|\RuntimeException $other): void
{
}

?>
-----
<?php

namespace unit\DurableRector\Fixture\Unmigratable;

use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\TerminatedFailure;

class FailureHandler
{
    // durable-rector: ApplicationFailure has no Durable counterpart — a catch on it never matches after migration; decide by hand
    public function handle(ApplicationFailure $failure): void
    {
    }
}

// durable-rector: TerminatedFailure has no Durable counterpart — a catch on it never matches after migration; decide by hand
// durable-rector: ServerFailure has no Durable counterpart — a catch on it never matches after migration; decide by hand
function report(?TerminatedFailure $failure, \Temporal\Exception\Failure\ServerFailure|\RuntimeException $other): void
{
}

?>
