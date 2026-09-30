<?php

namespace unit\DurableRector\Fixture\DurableUpgrade;

use Gplanchat\Durable\WorkflowEnvironment;

final class LogsItsId
{
    public function __invoke(WorkflowEnvironment $env): array
    {
        return ['executionId' => $env->executionId()];
    }
}

?>
-----
<?php

namespace unit\DurableRector\Fixture\DurableUpgrade;

use Gplanchat\Durable\WorkflowEnvironment;

final class LogsItsId
{
    public function __invoke(WorkflowEnvironment $env): array
    {
        return ['executionId' => $env->executionId()->toString()];
    }
}

?>
