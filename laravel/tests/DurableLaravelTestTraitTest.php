<?php

declare(strict_types=1);

namespace Tests;

use Gplanchat\Durable\Attribute\AsWorkflow;
use Gplanchat\Durable\Attribute\AsWorkflowMethod;
use Gplanchat\Durable\Laravel\Testing\DurableLaravelTestTrait;
use Gplanchat\Durable\WorkflowEnvironment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Bootstrap\RegisterFacades;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Testing\TestCase;

#[AsWorkflow('test-helper-greet')]
final class GreetWorkflow
{
    public function __construct(private readonly WorkflowEnvironment $environment) {}

    #[AsWorkflowMethod]
    public function run(string $name): string
    {
        $this->environment->sleep(0.05);

        return 'Hello, ' . $name . '!';
    }
}

#[AsWorkflow('test-helper-boom')]
final class BoomWorkflow
{
    #[AsWorkflowMethod]
    public function run(): never
    {
        throw new \InvalidArgumentException('no');
    }
}

/**
 * #983: the Laravel counterpart of `DurableBundleTestTrait`, booted in-process on the bench's
 * application with the memory backend.
 */
final class DurableLaravelTestTraitTest extends TestCase
{
    use DurableLaravelTestTrait;

    public function createApplication()
    {
        putenv('DURABLE_BACKEND=memory');
        $_ENV['DURABLE_BACKEND'] = $_SERVER['DURABLE_BACKEND'] = 'memory';
        putenv('CACHE_STORE=array');
        $_ENV['CACHE_STORE'] = $_SERVER['CACHE_STORE'] = 'array';

        $app = require \dirname(__DIR__) . '/bootstrap/app.php';
        $app->make(Kernel::class);
        // The kernel's bootstrap in two halves, to declare the test workflows once the config is loaded.
        $app->bootstrapWith([LoadEnvironmentVariables::class, LoadConfiguration::class]);
        $app['config']->set('durable.workflows', [GreetWorkflow::class, BoomWorkflow::class]);
        $app->bootstrapWith([RegisterFacades::class, RegisterProviders::class, BootProviders::class]);

        return $app;
    }

    public function testAWorkflowStartsDrainsAndReturnsItsResult(): void
    {
        $executionId = $this->dispatchWorkflow(GreetWorkflow::class, ['name' => 'World']);

        $this->drainUntilSettled($executionId);

        $this->assertWorkflowResultEquals($executionId, 'Hello, World!');
    }

    public function testAFailingWorkflowIsAssertedOnTheJournal(): void
    {
        $executionId = $this->dispatchWorkflow(BoomWorkflow::class);

        $this->drainUntilSettled($executionId);

        $this->assertWorkflowFailed($executionId, \InvalidArgumentException::class);
    }
}
