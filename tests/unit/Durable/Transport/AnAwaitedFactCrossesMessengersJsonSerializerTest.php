<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable\Transport;

use Gplanchat\Durable\Transport\AwaitedFact;
use Gplanchat\Durable\Transport\AwaitedFactKind;
use Gplanchat\Durable\Transport\ResumeWorkflowMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

/**
 * #627: a Messenger transport configured with `messenger.transport.symfony_serializer` carries a
 * resume as JSON, through the application's Symfony Serializer. The serializer here has the
 * normalizers FrameworkBundle registers that this message reaches: backed enums, arrays, objects.
 */
final class AnAwaitedFactCrossesMessengersJsonSerializerTest extends TestCase
{
    /**
     * @return iterable<string, array{AwaitedFact|null}>
     */
    public static function awaitedFacts(): iterable
    {
        yield 'an activity' => [AwaitedFact::activity('act-1')];
        yield 'a child' => [AwaitedFact::child('child-1')];
        yield 'a signal' => [AwaitedFact::signal('request-1')];
        yield 'timers' => [AwaitedFact::timers(['timer-1', 'timer-2'])];
        yield 'nothing' => [null];
    }

    #[DataProvider('awaitedFacts')]
    public function testAResumeReadsBackEqual(?AwaitedFact $awaited): void
    {
        $serializer = new Serializer(new SymfonySerializer(
            [new BackedEnumNormalizer(), new ArrayDenormalizer(), new ObjectNormalizer(propertyTypeExtractor: new ReflectionExtractor())],
            [new JsonEncoder()],
        ));
        $message = new ResumeWorkflowMessage('exec-1', [['name' => 'approve', 'arguments' => ['by' => 'ops']]], $awaited);

        $read = $serializer->decode($serializer->encode(new Envelope($message)))->getMessage();

        self::assertEquals($message, $read);
    }

    /**
     * Public for the denormalizer, the constructor still refuses what no factory builds.
     */
    public function testOnlyTimersAwaitSeveralIds(): void
    {
        self::assertSame(['timer-1', 'timer-2'], (new AwaitedFact(AwaitedFactKind::Timer, ['timer-1', 'timer-2']))->ids);

        $this->expectException(\InvalidArgumentException::class);
        new AwaitedFact(AwaitedFactKind::Activity, ['act-1', 'act-2']);
    }
}
