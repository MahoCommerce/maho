<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Pagination;

use ApiPlatform\State\Pagination\PaginatorInterface;

/**
 * Simple array-based paginator for API Platform
 *
 * @implements PaginatorInterface<mixed>
 * @implements \IteratorAggregate<int, mixed>
 */
final class ArrayPaginator implements PaginatorInterface, \IteratorAggregate
{
    /**
     * @param array<mixed> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly int $currentPage,
        private readonly int $itemsPerPage,
        private readonly int $totalItems,
    ) {}

    public function count(): int
    {
        return count($this->items);
    }

    public function getLastPage(): float
    {
        if ($this->itemsPerPage <= 0) {
            return 1.0;
        }
        return max(1.0, ceil($this->totalItems / $this->itemsPerPage));
    }

    public function getTotalItems(): float
    {
        return (float) $this->totalItems;
    }

    public function getCurrentPage(): float
    {
        return (float) $this->currentPage;
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->itemsPerPage;
    }

    /**
     * @return \ArrayIterator<int, mixed>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }
}
