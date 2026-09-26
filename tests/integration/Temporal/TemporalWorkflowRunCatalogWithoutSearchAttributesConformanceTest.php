<?php

declare(strict_types=1);

namespace integration\Temporal;

/**
 * The same suite on a host that leaves `search_attributes` off, as every host does by default:
 * nothing changes but the filters, which the catalog says it cannot apply and refuses (#558).
 */
final class TemporalWorkflowRunCatalogWithoutSearchAttributesConformanceTest extends TemporalWorkflowRunCatalogConformanceTest
{
    protected function searchAttributes(): bool
    {
        return false;
    }
}
