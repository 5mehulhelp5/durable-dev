<?php

namespace unit\DurableRector\Fixture\Unmigratable;

use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\TimeoutFailure;

class FailureWorkflow
{
    public function run($error)
    {
        try {
            $this->charge();
        } catch (TimeoutFailure $e) {
            return null;
        }

        if ($error instanceof \Temporal\Exception\Failure\ServerFailure) {
            throw new ApplicationFailure('declined', 'Declined', true);
        }
    }
}

?>
-----
<?php

namespace unit\DurableRector\Fixture\Unmigratable;

use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\TimeoutFailure;

class FailureWorkflow
{
    public function run($error)
    {
        // durable-rector: TimeoutFailure has no Durable counterpart — a catch on it never matches after migration; decide by hand
        try {
            $this->charge();
        } catch (TimeoutFailure $e) {
            return null;
        }

        // durable-rector: ServerFailure has no Durable counterpart — a catch on it never matches after migration; decide by hand
        if ($error instanceof \Temporal\Exception\Failure\ServerFailure) {
            // durable-rector: ApplicationFailure has no Durable counterpart — a catch on it never matches after migration; decide by hand
            throw new ApplicationFailure('declined', 'Declined', true);
        }
    }
}

?>
