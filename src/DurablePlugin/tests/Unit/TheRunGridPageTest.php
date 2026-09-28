<?php

declare(strict_types=1);

namespace Gplanchat\Durable\Plugin\Tests\Unit;

use Gplanchat\Durable\Plugin\Grid\RunGridPage;
use PHPUnit\Framework\TestCase;

/**
 * What the run grid iterates, and when its page says there is nothing to show (#383, slice B).
 */
final class TheRunGridPageTest extends TestCase
{
    public function testItIteratesTheRunsAndCarriesEachUnderRow(): void
    {
        $run = ['executionId' => 'exec-1', 'workflowName' => 'App\\OrderWorkflow', 'status' => 'failed'];

        $rows = iterator_to_array(new RunGridPage(['runs' => [$run], 'pagination' => ['hasNext' => false]]));

        self::assertCount(1, $rows);
        self::assertSame('exec-1', $rows[0]['executionId']);
        self::assertSame($run, $rows[0]['row'], 'a field that shows several facts reads the whole run');
    }

    public function testAnEmptyPageWithNothingAfterItIsTheEnd(): void
    {
        self::assertTrue((new RunGridPage(['runs' => [], 'pagination' => ['hasNext' => false]]))->isTheEnd());
        self::assertFalse((new RunGridPage(['runs' => [['executionId' => 'exec-1']], 'pagination' => ['hasNext' => false]]))->isTheEnd());
    }

    public function testAnEmptyPageThatHasANextOneIsNotTheEnd(): void
    {
        // A filtered page on Temporal can come back empty with a cursor (#557): "no runs" would be
        // a dead end where the next page may hold some.
        self::assertFalse((new RunGridPage(['runs' => [], 'pagination' => ['hasNext' => true]]))->isTheEnd());
    }
}
