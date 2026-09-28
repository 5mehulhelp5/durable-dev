<?php

declare(strict_types=1);

namespace Gplanchat\Durable\Plugin\Tests\Unit;

use Gplanchat\Durable\Observation\BackendHealth;
use Gplanchat\Durable\Observation\RunDashboard;
use Gplanchat\Durable\Observation\WorkflowRunDescription;
use Gplanchat\Durable\Observation\WorkflowRunFilter;
use Gplanchat\Durable\Observation\WorkflowRunPage;
use Gplanchat\Durable\Observation\WorkflowRunStatus;
use Gplanchat\Durable\Plugin\Controller\AdminDashboardController;
use Gplanchat\Durable\Port\WorkflowRunCatalogInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The run list pages forward only, with a way back to the first page (the user's decision on #383,
 * 2026-09-28): Temporal cannot page backwards, so there is no "previous page". A link from before,
 * which carried the stack of pages in `back`, lands on the first page rather than on a not-found.
 */
final class TheListPagesForwardOnlyTest extends TestCase
{
    public function testTheFirstPageOffersNoWayToTheFirstPage(): void
    {
        $pagination = $this->pagination([]);

        self::assertTrue($pagination['isFirstPage']);
        self::assertArrayNotHasKey('previous', $pagination);
        self::assertArrayNotHasKey('nextBack', $pagination);
    }

    public function testALaterPageOffersTheFirstPageAndTheNext(): void
    {
        $pagination = $this->pagination(['cursor' => 'c1']);

        self::assertFalse($pagination['isFirstPage']);
        self::assertTrue($pagination['hasNext']);
        self::assertArrayNotHasKey('previous', $pagination, 'no way back but to the first page');
    }

    public function testALinkFromBeforeLandsOnTheFirstPageWithItsFilters(): void
    {
        $response = $this->index(['status' => 'failed', 'executionIdPrefix' => 'ord', 'cursor' => 'c2', 'back' => 'WyIiLCJjMSJd']);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode());
        self::assertSame('/durable/runs?status=failed&executionIdPrefix=ord', $response->getTargetUrl());
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, mixed>
     */
    private function pagination(array $query): array
    {
        return json_decode((string) $this->index($query)->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /** @param array<string, string> $query */
    private function index(array $query): Response
    {
        $twig = new Environment(new ArrayLoader([
            '@SyliusAdmin/shared/layout/base.html.twig' => '',
            '@DurablePlugin/admin/dashboard/index.html.twig' => '{{ pagination|json_encode|raw }}',
        ]));

        return (new AdminDashboardController($twig))->index(Request::create('/durable/runs', 'GET', $query), new RunDashboard(new OnePageCatalog()));
    }
}

final class OnePageCatalog implements WorkflowRunCatalogInterface
{
    public function canFilterRuns(?WorkflowRunFilter $filter = null): bool
    {
        return true;
    }

    public function listRuns(?WorkflowRunStatus $status = null, ?string $cursor = null, int $limit = 20, ?WorkflowRunFilter $filter = null): WorkflowRunPage
    {
        return new WorkflowRunPage([], 'next');
    }

    public function findRun(string $executionId): ?WorkflowRunDescription
    {
        return null;
    }

    public function readHistory(WorkflowRunDescription $run): array
    {
        return [];
    }

    public function checkHealth(): BackendHealth
    {
        return new BackendHealth('Test backend', true, 'It answers.', new \DateTimeImmutable('@1700000000'), false);
    }
}
