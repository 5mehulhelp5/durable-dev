<?php

declare(strict_types=1);

namespace Gplanchat\Durable\Plugin\Grid;

/**
 * The run grid, as the controller sees it: a grid view to hand the admin's hooks, and the page
 * behind it. The one implementation, {@see SyliusRunGrid}, is the only class that names
 * sylius/grid-bundle; this seam is what keeps the controller analysable without that bundle.
 */
interface RunGridViews
{
    /**
     * @param array<string, mixed> $query the list page's query string
     *
     * @return array{resources: object, page: RunGridPage} `resources` is the grid-bundle GridView
     */
    public function view(array $query): array;
}
