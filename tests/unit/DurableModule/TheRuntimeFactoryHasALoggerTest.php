<?php

declare(strict_types=1);

namespace unit\DurableModule;

use Gplanchat\DurableModule\Runtime\RuntimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * An optional argument is not autowired: without this line, the factory gets null and the
 * Temporal client it builds logs nothing. Magento is not in the root graph, so this reads the
 * declaration.
 */
final class TheRuntimeFactoryHasALoggerTest extends TestCase
{
    public function testDiXmlHandsTheApplicationLoggerToTheFactory(): void
    {
        $di = simplexml_load_file(__DIR__ . '/../../../src/DurableModule/etc/di.xml');
        self::assertNotFalse($di);

        $logger = $di->xpath(\sprintf('//type[@name="%s"]/arguments/argument[@name="logger"]', RuntimeFactory::class));
        self::assertSame(LoggerInterface::class, trim((string) ($logger[0] ?? '')));
    }
}
