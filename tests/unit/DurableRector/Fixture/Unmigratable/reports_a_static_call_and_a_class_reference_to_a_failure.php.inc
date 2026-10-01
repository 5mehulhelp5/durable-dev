<?php

namespace unit\DurableRector\Fixture\Unmigratable;

use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\TerminatedFailure;

class FailureByNameWorkflow
{
    public function run()
    {
        $terminated = TerminatedFailure::class;

        throw ApplicationFailure::nonRetryable('declined');
    }
}

?>
-----
<?php

namespace unit\DurableRector\Fixture\Unmigratable;

use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\TerminatedFailure;

class FailureByNameWorkflow
{
    public function run()
    {
        // durable-rector: TerminatedFailure has no Durable counterpart — a catch on it never matches after migration; decide by hand
        $terminated = TerminatedFailure::class;

        // durable-rector: ApplicationFailure has no Durable counterpart — a catch on it never matches after migration; decide by hand
        throw ApplicationFailure::nonRetryable('declined');
    }
}

?>
