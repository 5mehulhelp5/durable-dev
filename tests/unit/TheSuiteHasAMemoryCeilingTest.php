<?php

declare(strict_types=1);

namespace unit\Gplanchat;

use PHPUnit\Framework\TestCase;

/**
 * A PHP CLI often ships with `memory_limit = -1`. A test that recurses without end then grows until
 * the kernel's OOM killer steps in, and on a desktop it takes the IDE down with it: four times on
 * 2026-09-28, a php process at 36 GB. phpunit.xml sets a ceiling so such a test fails on its own.
 */
final class TheSuiteHasAMemoryCeilingTest extends TestCase
{
    public function testTheSuiteRunsUnderAMemoryCeiling(): void
    {
        self::assertNotSame('-1', \ini_get('memory_limit'));
    }
}
