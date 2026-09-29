<?php

declare(strict_types=1);

namespace unit\DurableRector;

use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * The ports take an `ExecutionId`, not a string (#638): a call site that passes a string gets it
 * wrapped, one that already passes the value object is left alone.
 */
final class ExecutionIdArgumentRectorTest extends AbstractRectorTestCase
{
    #[DataProvider('provideData')]
    public function testStringArgumentsAreWrapped(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    public static function provideData(): \Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/Fixture/ExecutionId');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/execution-id.php';
    }
}
