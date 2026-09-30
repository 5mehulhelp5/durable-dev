<?php

declare(strict_types=1);

use Gplanchat\Durable\Rector\Rector\ExecutionIdReturnValueRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([ExecutionIdReturnValueRector::class]);
