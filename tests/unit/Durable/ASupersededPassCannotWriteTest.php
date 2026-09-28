<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable;

use Gplanchat\Durable\Attribute\AsWorkflow;
use Gplanchat\Durable\Attribute\AsWorkflowMethod;
use Gplanchat\Durable\Event\ExecutionStarted;
use Gplanchat\Durable\Exception\SupersededPassException;
use Gplanchat\Durable\ExecutionEngine;
use Gplanchat\Durable\ExecutionRuntime;
use Gplanchat\Durable\Handler\ResumeWorkflowHandler;
use Gplanchat\Durable\Port\NullWorkflowResumeDispatcher;
use Gplanchat\Durable\Port\WorkflowTimerDispatcher;
use Gplanchat\Durable\RegistryActivityExecutor;
use Gplanchat\Durable\Store\InMemoryChildWorkflowParentLinkStore;
use Gplanchat\Durable\Store\InMemoryEventStore;
use Gplanchat\Durable\Store\InMemoryWorkflowMetadataStore;
use Gplanchat\Durable\Store\PassEventStore;
use Gplanchat\Durable\Transport\InMemoryActivityTransport;
use Gplanchat\Durable\Transport\ResumeWorkflowMessage;
use Gplanchat\Durable\Workflow\WorkflowDefinitionLoader;
use Gplanchat\Durable\WorkflowEnvironment;
use Gplanchat\Durable\WorkflowRegistry;
use PHPUnit\Framework\TestCase;
use unit\Durable\Fixtures\SuiteActivities;

/**
 * #505, DUR053: a pass whose lock expired, and which a second resume has taken over, can no longer
 * write to the journal, however much of the refusal its workflow code catches.
 */
final class ASupersededPassCannotWriteTest extends TestCase
{
    private InMemoryEventStore $store;
    private InMemoryActivityTransport $activities;
    private ExecutionEngine $engine;

    protected function setUp(): void
    {
        $this->store = new InMemoryEventStore();
        $this->activities = new InMemoryActivityTransport();
        $this->engine = new ExecutionEngine(
            $this->store,
            new ExecutionRuntime($this->store, $this->activities, new RegistryActivityExecutor(), 0, null, true),
        );
        OvertakenWorkflow::$store = $this->store;
    }

    public function testWorkflowCodeThatCatchesTheRefusalStillWritesNothing(): void
    {
        $store = $this->store;
        $handler = static function (WorkflowEnvironment $env) use ($store): string {
            PassEventStore::open($store, 'exec-1'); // a second resume takes the execution over

            try {
                $env->activityStub(SuiteActivities::class)->echoValue('late');
            } catch (\Throwable) {
            }

            return 'done';
        };

        try {
            $this->engine->start('exec-1', $handler);
            self::fail('the superseded pass must not complete the run');
        } catch (SupersededPassException) {
        }

        self::assertSame([ExecutionStarted::class], array_map(static fn(object $e): string => $e::class, iterator_to_array($this->store->readStream('exec-1'), false)));
        self::assertTrue($this->activities->isEmpty(), 'nothing was dispatched');
    }

    public function testTheResumeHandlerStopsWithoutEndingTheRun(): void
    {
        $metadata = new InMemoryWorkflowMetadataStore();
        $metadata->save('exec-2', OvertakenWorkflow::class, []);
        $registry = new WorkflowRegistry();
        $registry->registerClass(OvertakenWorkflow::class);

        (new ResumeWorkflowHandler(
            $this->engine,
            $registry,
            $metadata,
            new NullWorkflowResumeDispatcher(),
            $this->store,
            new InMemoryChildWorkflowParentLinkStore(),
            new class implements WorkflowTimerDispatcher {
                public function dispatchTimerFire(string $executionId, int $delayMs = 0): void {}
            },
            new WorkflowDefinitionLoader(),
        ))(new ResumeWorkflowMessage('exec-2'));

        self::assertTrue($metadata->hasActiveWorkflowMetadata('exec-2'), 'the newer pass owns the run; it is not ended');
        self::assertSame(0, $this->store->countEventsInStream('exec-2'));
    }
}

#[AsWorkflow(name: 'test.overtaken')]
final class OvertakenWorkflow
{
    public static InMemoryEventStore $store;

    #[AsWorkflowMethod]
    public function run(): string
    {
        PassEventStore::open(self::$store, 'exec-2'); // a second resume takes the execution over

        return 'done';
    }
}
