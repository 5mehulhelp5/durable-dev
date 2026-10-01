<?php

namespace unit\DurableRector\Fixture\Unmigratable;

use Temporal\Exception\Failure\TimeoutFailure;
use Temporal\Promise;

class AlreadyMarkedWorkflow
{
    public function run($promises)
    {
        // durable-rector: TimeoutFailure has no Durable counterpart — once temporal/sdk is removed this reference no longer resolves; decide by hand
        try {
            // durable-rector: some() without a count — pass the count to $env->some() by hand
            return yield Promise::some($promises);
        } catch (TimeoutFailure $e) {
            return null;
        }
    }
}

?>
