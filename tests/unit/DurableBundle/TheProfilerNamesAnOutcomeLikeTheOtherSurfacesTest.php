<?php

declare(strict_types=1);

namespace unit\Gplanchat\DurableBundle;

use Gplanchat\Durable\Bundle\DataCollector\DurableDataCollector;
use Gplanchat\Durable\Bundle\Profiler\DurableExecutionTrace;
use Gplanchat\Durable\Event\ExecutionCompleted;
use Gplanchat\Durable\Event\WorkflowContinuedAsNew;
use Gplanchat\Durable\ExecutionId;
use Gplanchat\Durable\Store\InMemoryEventStore;
use Gplanchat\Durable\Store\InMemoryWorkflowMetadataStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One vocabulary on every dashboard (#821): the panel names an outcome as the Sylius, Magento and
 * Filament pages do. It keeps its own words only for the states the outcome enum does not have.
 */
final class TheProfilerNamesAnOutcomeLikeTheOtherSurfacesTest extends TestCase
{
    public function testACompletedRunIsCompleted(): void
    {
        $events = new InMemoryEventStore();
        $events->append(new ExecutionCompleted(ExecutionId::fromString('exec-1'), 'ok'));

        self::assertSame('Completed', $this->label($events));
    }

    public function testAContinuedRunIsContinuedAsNew(): void
    {
        $events = new InMemoryEventStore();
        $events->append(new WorkflowContinuedAsNew(ExecutionId::fromString('exec-1'), 'App\\OrderWorkflow', []));

        self::assertSame('Continued as new', $this->label($events));
    }

    private function label(InMemoryEventStore $events): string
    {
        $collector = new DurableDataCollector(new DurableExecutionTrace(), new InMemoryWorkflowMetadataStore(), $events);
        $collector->collect(new Request(['durable_execution' => 'exec-1']), new Response());

        return $collector->getExecutionsDetail()[0]['executionStatusLabel'];
    }
}
