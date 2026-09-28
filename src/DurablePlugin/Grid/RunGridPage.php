<?php

declare(strict_types=1);

namespace Gplanchat\Durable\Plugin\Grid;

/**
 * What the run grid's data provider hands the grid: the rows it iterates, and the rest of the
 * listing model the page around it needs (counters, cursor, filters), from one catalog call.
 *
 * Not a Pagerfanta: the catalog pages by cursor, with no total to count (#383, the user's
 * decision), so the page renders its own previous and next links.
 *
 * @implements \IteratorAggregate<int, array<string, mixed>>
 */
final readonly class RunGridPage implements \IteratorAggregate, \Countable
{
    /**
     * @param array<string, mixed> $model {@see \Gplanchat\Durable\Observation\RunDashboard::listing()}
     */
    public function __construct(public array $model) {}

    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->runs());
    }

    public function count(): int
    {
        return \count($this->runs());
    }

    /**
     * Nothing on this page and nothing after it: the one case for "no runs". A filtered page on
     * Temporal can come back empty with a cursor (#557), and then the way on is the next page.
     */
    public function isTheEnd(): bool
    {
        $pagination = $this->model['pagination'] ?? [];

        return 0 === $this->count() && !(\is_array($pagination) && true === ($pagination['hasNext'] ?? false));
    }

    /**
     * Each run as the listing described it, plus itself under `row`: a grid field reads one path,
     * and the ones that show several facts of a run (its link, its waiting notes) read that one.
     *
     * @return list<array<string, mixed>>
     */
    private function runs(): array
    {
        $runs = $this->model['runs'] ?? [];
        if (!\is_array($runs)) {
            return [];
        }

        return array_values(array_map(
            static fn(array $run): array => $run + ['row' => $run],
            array_filter($runs, \is_array(...)),
        ));
    }
}
