<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable\Laravel;

use Gplanchat\Durable\Attribute\AsActivityMethod;
use Gplanchat\Durable\Attribute\AsWorkflow;
use Gplanchat\Durable\Attribute\AsWorkflowMethod;
use Gplanchat\Durable\ExecutionId;
use Gplanchat\Durable\Laravel\DurableServiceProvider;
use Gplanchat\Durable\Port\WorkflowResumeDispatcher;
use Gplanchat\Durable\RegistryActivityExecutor;
use Gplanchat\Durable\Store\WorkflowMetadataStore;
use Gplanchat\Durable\WorkflowEnvironment;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

interface TwoStepActivities
{
    #[AsActivityMethod('two.first')]
    public function first(): string;

    #[AsActivityMethod('two.second')]
    public function second(string $after): string;
}

#[AsWorkflow('two-step')]
final class TwoStepWorkflow
{
    public function __construct(private readonly WorkflowEnvironment $environment) {}

    #[AsWorkflowMethod]
    public function run(): string
    {
        $activities = $this->environment->activityStub(TwoStepActivities::class);

        return $this->environment->await($activities->second($this->environment->await($activities->first())));
    }
}

#[AsWorkflow('short-nap')]
final class ShortNapWorkflow
{
    public function __construct(private readonly WorkflowEnvironment $environment) {}

    #[AsWorkflowMethod]
    public function run(): string
    {
        $this->environment->sleep(0.1);

        return 'awake';
    }
}

/**
 * #603, the user's decision: on Laravel's memory backend, `dispatchNewWorkflowRun()` drives the
 * run in the caller's process, as the Symfony bundle's in-memory mode does. It used to be the null
 * dispatcher, and the run never started.
 */
final class AMemoryRunCompletesInTheCallersProcessTest extends TestCase
{
    public function testARunWithTwoActivitiesCompletesInTheCall(): void
    {
        $app = $this->memory([TwoStepWorkflow::class]);
        $ran = [];
        $activities = $app->make(RegistryActivityExecutor::class);
        $activities->register('two.first', static function () use (&$ran): string {
            $ran[] = 'first';

            return 'a';
        });
        $activities->register('two.second', static function () use (&$ran): string {
            $ran[] = 'second';

            return 'b';
        });

        $app->make(WorkflowResumeDispatcher::class)->dispatchNewWorkflowRun(ExecutionId::fromString('run-1'), 'two-step', []);

        self::assertTrue($app->make(WorkflowMetadataStore::class)->get(ExecutionId::fromString('run-1'))['completed'] ?? false);
        self::assertSame(['first', 'second'], $ran);
    }

    public function testARunThatSleepsWakesInTheCall(): void
    {
        $app = $this->memory([ShortNapWorkflow::class]);

        $app->make(WorkflowResumeDispatcher::class)->dispatchNewWorkflowRun(ExecutionId::fromString('run-2'), 'short-nap', []);

        self::assertTrue($app->make(WorkflowMetadataStore::class)->get(ExecutionId::fromString('run-2'))['completed'] ?? false);
    }

    /**
     * @param list<class-string> $workflows
     */
    private function memory(array $workflows): Container
    {
        $app = new Container();
        $app->instance('config', new \ArrayObject(['durable' => ['backend' => 'memory', 'workflows' => $workflows]], \ArrayObject::ARRAY_AS_PROPS));
        (new DurableServiceProvider($app))->register();

        return $app;
    }
}
