<?php

declare(strict_types=1);

namespace unit\DurableRector;

use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * The getters that now return an `ExecutionId` (#682) are read through `->toString()`, so the
 * code that read a string keeps reading one.
 */
final class ExecutionIdReturnValueRectorTest extends AbstractRectorTestCase
{
    #[DataProvider('provideData')]
    public function testTheStringIsKept(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    public static function provideData(): \Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/Fixture/ExecutionIdReturn');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/execution-id-return.php';
    }
}
