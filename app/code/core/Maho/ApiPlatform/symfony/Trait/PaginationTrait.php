<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use ApiPlatform\State\Pagination\TraversablePaginator;

/**
 * Extracts pagination parameters (page, pageSize) from API Platform context.
 *
 * Handles both REST query parameters ($context['filters']) and GraphQL arguments
 * ($context['args']), normalizing them into a consistent format with configurable
 * defaults and maximum page size clamping.
 */
trait PaginationTrait
{
    /**
     * @return array{page: int, pageSize: int}
     */
    protected function extractPagination(array $context, int $defaultPageSize = 20, int $maxPageSize = 100): array
    {
        $filters = $context['args'] ?? $context['filters'] ?? [];

        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = max(1, min(
            (int) ($filters['itemsPerPage'] ?? $filters['pageSize'] ?? $defaultPageSize),
            $maxPageSize,
        ));

        return ['page' => $page, 'pageSize' => $pageSize];
    }

    /**
     * Wrap a single (or absent) item as a 0-or-1 element paginator.
     *
     * @template T of object
     * @param  T|null $item
     * @return TraversablePaginator<T>
     */
    protected function singleItemPaginator(?object $item): TraversablePaginator
    {
        $items = $item ? [$item] : [];
        return new TraversablePaginator(new \ArrayIterator($items), 1, 1, count($items));
    }
}
