<?php

declare(strict_types=1);

namespace unit\Gplanchat\DurableModule;

use Gplanchat\DurableModule\Ui\Component\Listing\Column\ProcessActions;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixture/magento-listing-column.php';
require_once __DIR__ . '/Fixture/magento-template-globals.php';

/**
 * #514: the grid's link to a run carries its execution id in the query string. An execution id may
 * hold a slash, and a route param is a path segment, which the slash would split: the run page
 * would then look up `order` and say it does not know it.
 */
final class TheGridLinksARunByItsExecutionIdTest extends TestCase
{
    public function testTheLinkCarriesTheExecutionIdInTheQueryString(): void
    {
        $urls = new class implements UrlInterface {
            /** @var list<array{?string, ?array<string, mixed>}> */
            public array $asked = [];

            public function getUrl(?string $routePath = null, ?array $routeParams = null): string
            {
                $this->asked[] = [$routePath, $routeParams];

                return 'url';
            }
        };
        $column = new ProcessActions($this->createStub(ContextInterface::class), new UiComponentFactory(), $urls, [], ['name' => 'actions']);

        $column->prepareDataSource(['data' => ['items' => [['run_id' => 'server-run-1', 'execution_id' => 'order/42']]]]);

        self::assertSame([['durable/process/view', ['_query' => ['run_id' => 'order/42']]]], $urls->asked, 'no route param: a path segment would split the id at its slash');
    }
}
