<?php

declare(strict_types=1);

namespace Gplanchat\Durable\Filament\Tests;

use Gplanchat\Durable\Observation\NexusOperationState;
use Gplanchat\Durable\Observation\NexusOperationSummary;
use Gplanchat\Durable\Observation\WorkflowRunDescription;
use Gplanchat\Durable\Observation\WorkflowRunEvent;
use Gplanchat\Durable\Observation\WorkflowRunEventKind;
use Gplanchat\Durable\Observation\WorkflowRunStatus;
use Gplanchat\Durable\Port\WorkflowRunCatalogInterface;

final class TheRunPageTest extends PanelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(WorkflowRunCatalogInterface::class, new FakeCatalog(
            [new WorkflowRunDescription('run-1', 'App\\OrderWorkflow', WorkflowRunStatus::Running, waitingOn: 'payment-received', executionId: 'order-1')],
            histories: ['order-1' => [
                new WorkflowRunEvent(1, new \DateTimeImmutable('2026-09-29 09:00:00'), WorkflowRunEventKind::Execution, 'Workflow started'),
                new WorkflowRunEvent(2, new \DateTimeImmutable('2026-09-29 09:00:01'), WorkflowRunEventKind::Activity, 'chargeCard scheduled', ['amount' => 42], actionKey: 'activity:1'),
            ]],
        ));
    }

    public function testItShowsTheStatusTheWaitAndTheHistory(): void
    {
        $this->get('/admin/durable/run?executionId=order-1')
            ->assertOk()
            ->assertSee('App\\OrderWorkflow')
            ->assertSee('Running')
            ->assertSee('waiting on payment-received')
            ->assertSee('Workflow started')
            ->assertSee('chargeCard scheduled')
            ->assertSee('&quot;amount&quot;: 42', false);
    }

    public function testAnUnknownRunIsSaidAsSuch(): void
    {
        $this->get('/admin/durable/run?executionId=nope')
            ->assertOk()
            ->assertSee('No run with this id on this backend.');
    }

    public function testTheNexusOperationsReadWhereTheyAreServedAndWhetherTheyAreSettled(): void
    {
        $html = view('durable-filament::nexus-operations', ['operations' => [
            new NexusOperationSummary('stock-endpoint', 'stock', 'reserve', NexusOperationState::InFlight),
            new NexusOperationSummary('billing-endpoint', 'billing', 'charge', NexusOperationState::TimedOut),
        ]])->render();

        self::assertStringContainsString('stock-endpoint', $html);
        self::assertStringContainsString('reserve', $html);
        self::assertStringContainsString('in flight', $html);
        self::assertStringContainsString('timed out', $html);
    }

    public function testTheNexusStatesSpeakFrench(): void
    {
        $this->app->setLocale('fr');

        $html = view('durable-filament::nexus-operations', ['operations' => [
            new NexusOperationSummary('stock-endpoint', 'stock', 'reserve', NexusOperationState::InFlight),
        ]])->render();

        self::assertStringContainsString('en cours', $html);
    }

    public function testNoNexusOperationMeansNoNexusSection(): void
    {
        self::assertSame('', trim(view('durable-filament::nexus-operations', ['operations' => []])->render()));
    }
}
