<?php

declare(strict_types=1);

namespace unit\DurableLaravel;

use Gplanchat\Durable\Laravel\DurableServiceProvider;
use Gplanchat\Durable\RegistryActivityExecutor;
use Gplanchat\Durable\Transport\ActivityMessage;
use Gplanchat\Durable\Transport\ActivityTransportInterface;
use Gplanchat\Durable\Worker\ActivityMessageProcessor;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use unit\DurableLaravel\Fixtures\FakeQueue;
use unit\DurableLaravel\Fixtures\FakeQueueFactory;

/**
 * `max_activity_retries` was hard-coded to `0` on Laravel, configurable on Symfony and Magento
 * (#357). It is read where the attempts are decided: the processor `queue:work` runs.
 */
final class MaxActivityRetriesTest extends TestCase
{
    public function testTheCeilingStopsTheRetriesOfAFailingActivity(): void
    {
        $app = $this->registered(['max_activity_retries' => 1]);

        // Attempt 2 is the one retry a ceiling of 1 allows: it fails, nothing is queued after it.
        $this->failAttempt($app, 2);

        self::assertTrue($app->make(ActivityTransportInterface::class)->isEmpty());
    }

    public function testWithoutTheKeyAnActivityKeepsRetrying(): void
    {
        $app = $this->registered([]);

        $this->failAttempt($app, 2);

        self::assertFalse($app->make(ActivityTransportInterface::class)->isEmpty(), 'unlimited by default, Temporal semantics');
    }

    public function testANegativeCeilingIsRefusedByName(): void
    {
        $app = $this->registered(['max_activity_retries' => -1]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('durable.max_activity_retries');

        $app->make(ActivityMessageProcessor::class);
    }

    /** @param array<string, mixed> $durable */
    private function registered(array $durable): Container
    {
        $app = new Container();
        $app->instance(\Illuminate\Contracts\Queue\Factory::class, new FakeQueueFactory(new FakeQueue()));
        $app->instance('config', new \ArrayObject(['durable' => ['backend' => 'memory'] + $durable], \ArrayObject::ARRAY_AS_PROPS));
        (new DurableServiceProvider($app))->register();

        return $app;
    }

    private function failAttempt(Container $app, int $attempt): void
    {
        $app->make(RegistryActivityExecutor::class)->register('Charge', static fn(): never => throw new \RuntimeException('declined'));

        $app->make(ActivityMessageProcessor::class)->process(new ActivityMessage('exec-1', 'act-1', 'Charge', [], attempt: $attempt));
    }
}
