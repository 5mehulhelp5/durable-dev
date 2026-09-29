<?php

declare(strict_types=1);

namespace Gplanchat\Durable\Filament\Pages;

use Gplanchat\Durable\Observation\RunDashboard;
use Livewire\Attributes\Url;

/**
 * One run, by the id the application started it with (#514): its status, what it waits on, its
 * history, and its Nexus operations.
 *
 * The Nexus operations are `nexusOperations` in the run model, which a catalog that can hold them
 * fills (#707). Until the model carries the key, the section is not shown: a journal cannot hold a
 * Nexus operation (DUR036), and rebuilding one from the history's rows would re-decide what the
 * projection settles (DUR049).
 */
final class Run extends DurablePage
{
    protected static ?string $slug = 'durable/run';

    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    public string $executionId = '';

    public function getTitle(): string
    {
        return (string) __('durable-filament::durable.run.title');
    }

    protected function durableView(): string
    {
        return 'durable-filament::run';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        if ('' === $this->executionId) {
            return ['run' => null];
        }

        return app(RunDashboard::class)->run($this->executionId);
    }
}
