<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable\Bundle\Messenger;

use Gplanchat\Durable\Bundle\DependencyInjection\Compiler\RegisterDurableMiddlewarePass;
use Gplanchat\Durable\Bundle\DependencyInjection\DurableExtension;
use Gplanchat\Durable\Bundle\Messenger\EarlyResumeMiddleware;
use Gplanchat\Durable\Exception\ResumeArrivedBeforeItsOutcome;
use Gplanchat\Durable\Transport\AwaitedFact;
use Gplanchat\Durable\Transport\ResumeWorkflowMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * #606: under DUR050 a resume is sent before the outcome it announces, and again after it. The early
 * one that keeps finding its outcome missing ran out of retries and landed in the failure transport,
 * where it read as a lost run, although the resume sent after the append carried the execution.
 */
final class AnExhaustedEarlyResumeIsDroppedTest extends TestCase
{
    public function testAnEarlyResumeIsRetriedWhateverTheTransportsRetryLimit(): void
    {
        $this->expectException(RecoverableMessageHandlingException::class);

        $this->bus(new RecordingLogger())->dispatch(new Envelope(new ResumeWorkflowMessage('exec-1', [], AwaitedFact::activity('act-1'))));
    }

    public function testPastTheCeilingItIsAcknowledgedWithAnInfoLog(): void
    {
        $logger = new RecordingLogger();

        $this->bus($logger)->dispatch(new Envelope(new ResumeWorkflowMessage('exec-1', [], AwaitedFact::activity('act-1')), [new RedeliveryStamp(EarlyResumeMiddleware::MAX_RETRIES)]));

        self::assertCount(1, $logger->records);
        self::assertSame('info', $logger->records[0][0]);
        self::assertStringContainsString('exec-1', $logger->records[0][1]);
    }

    public function testAnyOtherFailureIsLeftAlone(): void
    {
        $bus = new MessageBus([
            new EarlyResumeMiddleware(new RecordingLogger()),
            new HandleMessageMiddleware(new HandlersLocator([
                ResumeWorkflowMessage::class => [static fn(): never => throw new \RuntimeException('journal down')],
            ])),
        ]);

        $this->expectException(HandlerFailedException::class);

        $bus->dispatch(new Envelope(new ResumeWorkflowMessage('exec-1', [], AwaitedFact::activity('act-1')), [new RedeliveryStamp(EarlyResumeMiddleware::MAX_RETRIES)]));
    }

    public function testTheBundleInstallsItWhereResumesRideMessenger(): void
    {
        $container = new ContainerBuilder();
        (new DurableExtension())->load([['backend' => 'dbal']], $container);

        $definition = $container->getDefinition('durable.messenger.early_resume');
        self::assertSame(EarlyResumeMiddleware::class, $definition->getClass());
        self::assertSame([['priority' => 95]], $definition->getTag(RegisterDurableMiddlewarePass::TAG));
    }

    public function testOnNativeTemporalThereIsNoMessengerResumeToWatch(): void
    {
        $container = new ContainerBuilder();
        (new DurableExtension())->load([['backend' => 'temporal', 'temporal' => ['dsn' => 'temporal://127.0.0.1:7233?namespace=default']]], $container);

        self::assertFalse($container->hasDefinition('durable.messenger.early_resume'));
    }

    private function bus(RecordingLogger $logger): MessageBus
    {
        return new MessageBus([
            new EarlyResumeMiddleware($logger),
            new HandleMessageMiddleware(new HandlersLocator([
                ResumeWorkflowMessage::class => [static fn(ResumeWorkflowMessage $m): never => throw new ResumeArrivedBeforeItsOutcome($m->executionId, AwaitedFact::activity('act-1'))],
            ])),
        ]);
    }
}

final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{string, string}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [(string) $level, strtr((string) $message, array_map(static fn(mixed $v): string => \is_scalar($v) ? (string) $v : '', array_combine(array_map(static fn(string $k): string => '{' . $k . '}', array_keys($context)), $context)))];
    }
}
