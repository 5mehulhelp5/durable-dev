<?php

declare(strict_types=1);

use Gplanchat\Durable\Rector\Rector\ExecutionIdArgumentRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()->withRules([ExecutionIdArgumentRector::class]);
