<?php

declare(strict_types=1);

/*
 * The Magento types a listing column stands on, reduced to what `ProcessActions` calls. Magento is
 * not installed in the root suite; declared only when absent, so a Magento checkout keeps its own.
 */

namespace Magento\Framework;

if (!interface_exists(UrlInterface::class)) {
    interface UrlInterface
    {
        /**
         * @param array<string, mixed>|null $routeParams
         */
        public function getUrl(?string $routePath = null, ?array $routeParams = null): string;
    }
}

namespace Magento\Framework\View\Element\UiComponent;

if (!interface_exists(ContextInterface::class)) {
    interface ContextInterface {}
}

namespace Magento\Framework\View\Element;

if (!class_exists(UiComponentFactory::class)) {
    class UiComponentFactory {}
}

namespace Magento\Ui\Component\Listing\Columns;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;

if (!class_exists(Column::class)) {
    class Column
    {
        /**
         * @param array<string, mixed> $components
         * @param array<string, mixed> $data
         */
        public function __construct(
            protected ContextInterface $context,
            protected UiComponentFactory $uiComponentFactory,
            protected array $components = [],
            protected array $data = [],
        ) {}

        public function getData(string $key): mixed
        {
            return $this->data[$key] ?? null;
        }
    }
}
