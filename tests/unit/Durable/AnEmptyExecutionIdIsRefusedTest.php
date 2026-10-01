<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable;

use Gplanchat\Durable\ExecutionContext;
use Gplanchat\Durable\ExecutionEngine;
use Gplanchat\Durable\ExecutionId;
use Gplanchat\Durable\ExecutionRuntime;
use Gplanchat\Durable\Handler\FireWorkflowTimersHandler;
use Gplanchat\Durable\Handler\ResumeWorkflowHandler;
use Gplanchat\Durable\Port\WorkflowLifecycleInterface;
use Gplanchat\Durable\Port\WorkflowResumeDispatcher;
use Gplanchat\Durable\Port\WorkflowTimerDispatcher;
use Gplanchat\Durable\RegistryActivityExecutor;
use Gplanchat\Durable\Store\ChildWorkflowParentLinkStoreInterface;
use Gplanchat\Durable\Store\EventStoreCommandBuffer;
use Gplanchat\Durable\Store\EventStoreHistorySource;
use Gplanchat\Durable\Store\EventStoreInterface;
use Gplanchat\Durable\Store\InMemoryEventStore;
use Gplanchat\Durable\Store\PassEventStore;
use Gplanchat\Durable\Store\WorkflowMetadataStore;
use Gplanchat\Durable\Transport\FireWorkflowTimersMessage;
use Gplanchat\Durable\Transport\InMemoryActivityTransport;
use Gplanchat\Durable\Transport\ResumeWorkflowMessage;
use Gplanchat\Durable\Worker\WorkflowFiberDriver;
use Gplanchat\Durable\Workflow\WorkflowDefinitionLoader;
use Gplanchat\Durable\WorkflowEnvironment;
use Gplanchat\Durable\WorkflowRegistry;
use PHPUnit\Framework\TestCase;

/**
 * M8 (#329): an empty identifier names no execution, and every store would file it under "".
 *
 * Two helpers keep a string id until #682's last part, and convert it on entry: an empty id is
 * refused there too, whatever the store behind (UPGRADE).
 */
final class AnEmptyExecutionIdIsRefusedTest extends TestCase
{
    public function testAnEmptyStringIsNotAnExecutionId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ExecutionId::fromString('');
    }

    public function testAnyOtherStringIs(): void
    {
        self::assertSame('exec-1', ExecutionId::fromString('exec-1')->toString());
    }

    public function testAPassOverAFencedStoreRefusesAnEmptyId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PassEventStore::open(new InMemoryEventStore(), '');
    }

    public function testAPassOverAStoreThatCannotFenceRefusesAnEmptyIdToo(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PassEventStore::open($this->createStub(EventStoreInterface::class), '');
    }

    public function testTheFiberDriverRefusesAnEmptyIdBeforeTheLifecycleHearsOfIt(): void
    {
        $store = new InMemoryEventStore();
        $transport = new InMemoryActivityTransport();
        $runtime = new ExecutionRuntime($store, $transport, new RegistryActivityExecutor(), 0, null, true);
        $context = new ExecutionContext(
            ExecutionId::fromString('exec-1'),
            new EventStoreHistorySource($store, 'exec-1'),
            new EventStoreCommandBuffer($store, $transport, ExecutionId::fromString('exec-1')),
        );
        $lifecycle = $this->createMock(WorkflowLifecycleInterface::class);
        $lifecycle->expects(self::never())->method('onBeforeRun');

        $this->expectException(\InvalidArgumentException::class);

        (new WorkflowFiberDriver($lifecycle))->run('', $context, new WorkflowEnvironment($context, $runtime), static fn(): null => null);
    }

    public function testATimerMessageWithAnEmptyIdIsRefusedOverAFencedStore(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->timersHandler(new InMemoryEventStore())(new FireWorkflowTimersMessage(''));
    }

    public function testATimerMessageWithAnEmptyIdIsRefusedOverAStoreThatCannotFence(): void
    {
        $store = $this->createMock(EventStoreInterface::class);
        $store->expects(self::never())->method(self::anything());

        $this->expectException(\InvalidArgumentException::class);

        $this->timersHandler($store)(new FireWorkflowTimersMessage(''));
    }

    public function testAResumeMessageWithAnEmptyIdIsRefusedBeforeAnyStoreHearsOfIt(): void
    {
        $store = $this->createMock(EventStoreInterface::class);
        $store->expects(self::never())->method(self::anything());
        $metadata = $this->createMock(WorkflowMetadataStore::class);
        $metadata->expects(self::never())->method(self::anything());

        $this->expectException(\InvalidArgumentException::class);

        (new ResumeWorkflowHandler(
            new ExecutionEngine($store, $this->runtime($store)),
            new WorkflowRegistry(),
            $metadata,
            $this->createStub(WorkflowResumeDispatcher::class),
            $store,
            $this->createStub(ChildWorkflowParentLinkStoreInterface::class),
            $this->createStub(WorkflowTimerDispatcher::class),
            new WorkflowDefinitionLoader(),
        ))(new ResumeWorkflowMessage(''));
    }

    private function timersHandler(EventStoreInterface $store): FireWorkflowTimersHandler
    {
        return new FireWorkflowTimersHandler(
            $store,
            $this->runtime($store),
            $this->createStub(WorkflowResumeDispatcher::class),
            $this->createStub(WorkflowTimerDispatcher::class),
        );
    }

    private function runtime(EventStoreInterface $store): ExecutionRuntime
    {
        return new ExecutionRuntime($store, new InMemoryActivityTransport(), new RegistryActivityExecutor(), 0, null, true);
    }
}
