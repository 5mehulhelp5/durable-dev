<?php

declare(strict_types=1);

namespace unit\DurableModule;

use Gplanchat\DurableModule\Runtime\RuntimeFactory;
use PHPUnit\Framework\TestCase;

/**
 * An optional argument is not autowired (DUR055): the codec is declared, `null` in the published
 * package, so a shop sees the line it overrides in its own `di.xml`. Magento is not in the root
 * graph, so this reads the declaration.
 */
final class TheRuntimeFactoryCodecIsDeclaredTest extends TestCase
{
    public function testDiXmlDeclaresTheCodecArgumentAsNull(): void
    {
        $di = simplexml_load_file(__DIR__ . '/../../../src/DurableModule/etc/di.xml');
        self::assertNotFalse($di);

        $codec = $di->xpath(\sprintf('//type[@name="%s"]/arguments/argument[@name="codec"]', RuntimeFactory::class));
        self::assertCount(1, $codec);
        self::assertSame('null', (string) $codec[0]->attributes('http://www.w3.org/2001/XMLSchema-instance')['type']);
    }
}
