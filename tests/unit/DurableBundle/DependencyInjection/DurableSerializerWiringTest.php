<?php

declare(strict_types=1);

namespace unit\Gplanchat\DurableBundle\DependencyInjection;

use Gplanchat\Durable\Bundle\DependencyInjection\DurableExtension;
use Gplanchat\Durable\Bundle\Serializer\DurationNormalizer;
use Gplanchat\Durable\Bundle\Serializer\RetryLimitNormalizer;
use Gplanchat\Durable\Bundle\Serializer\TaskQueueNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * #643: without these tags FrameworkBundle's serializer falls back to the ObjectNormalizer, which
 * recurses without end on Duration and RetryLimit and
 * loses a TaskQueue's name.
 */
final class DurableSerializerWiringTest extends TestCase
{
    public function testTheNormalizersJoinTheSerializer(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        (new DurableExtension())->load([[]], $container);

        self::assertSame(DurationNormalizer::class, $container->getDefinition('durable.serializer.duration_normalizer')->getClass());
        self::assertSame(RetryLimitNormalizer::class, $container->getDefinition('durable.serializer.retry_limit_normalizer')->getClass());
        self::assertSame(TaskQueueNormalizer::class, $container->getDefinition('durable.serializer.task_queue_normalizer')->getClass());
        foreach (['duration', 'retry_limit', 'task_queue'] as $name) {
            self::assertTrue($container->getDefinition("durable.serializer.{$name}_normalizer")->hasTag('serializer.normalizer'));
        }
    }
}
