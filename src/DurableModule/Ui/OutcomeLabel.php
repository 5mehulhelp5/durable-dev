<?php

declare(strict_types=1);

namespace Gplanchat\DurableModule\Ui;

use Gplanchat\Durable\Observation\WorkflowRunStatus;

/**
 * The words of the Sylius, Filament and profiler pages for an outcome (#821). Each phrase goes
 * through `__()` on its own line, so that Magento's phrase collector finds it and a dictionary can
 * translate it. It is a class of its own, free of any Magento interface, so that the grid, the run
 * page and the counters share it and a unit test can load it.
 */
final class OutcomeLabel
{
    public static function of(WorkflowRunStatus $status): string
    {
        return (string) match ($status) {
            WorkflowRunStatus::Running => __('Running'),
            WorkflowRunStatus::Completed => __('Completed'),
            WorkflowRunStatus::Failed => __('Failed'),
            WorkflowRunStatus::Cancelled => __('Cancelled'),
            WorkflowRunStatus::ContinuedAsNew => __('Continued as new'),
        };
    }
}
