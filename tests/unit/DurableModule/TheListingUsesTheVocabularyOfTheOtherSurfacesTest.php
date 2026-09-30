<?php

declare(strict_types=1);

namespace unit\Gplanchat\DurableModule;

use Gplanchat\Durable\Observation\WorkflowRunStatus;
use Gplanchat\DurableModule\Ui\OutcomeLabel;
use PHPUnit\Framework\TestCase;

/**
 * One vocabulary on every dashboard (#821): the grid says Outcome, Execution and Backend run, and
 * names an outcome as the Sylius, Filament and profiler pages do.
 */
final class TheListingUsesTheVocabularyOfTheOtherSurfacesTest extends TestCase
{
    public function testTheColumnsAreNamedAsOnTheOtherSurfaces(): void
    {
        $listing = simplexml_load_file(\dirname(__DIR__, 3) . '/src/DurableModule/view/adminhtml/ui_component/durable_process_listing.xml');
        self::assertInstanceOf(\SimpleXMLElement::class, $listing);

        $labels = [];
        foreach ($listing->xpath('//columns/column') ?: [] as $column) {
            $labels[(string) $column['name']] = (string) ($column->settings->label ?? '');
        }

        self::assertSame('Execution', $labels['execution_id']);
        self::assertSame('Backend run', $labels['run_id']);
        self::assertSame('Outcome', $labels['status']);
    }

    public function testAnOutcomeReadsAsOnTheOtherSurfaces(): void
    {
        require_once __DIR__ . '/Fixture/magento-template-globals.php';

        self::assertSame(
            ['Running', 'Completed', 'Failed', 'Cancelled', 'Continued as new'],
            array_map(OutcomeLabel::of(...), WorkflowRunStatus::cases()),
        );
    }
}
