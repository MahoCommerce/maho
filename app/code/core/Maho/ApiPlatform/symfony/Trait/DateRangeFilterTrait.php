<?php

/**
 * Shared `createdFrom` / `createdTo` / `updatedSince` collection filters.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

/**
 * Date-range filtering for collections, in one place because the two provider base
 * classes don't share one and the boundary handling is easy to get wrong.
 */
trait DateRangeFilterTrait
{
    use FilterValueTrait;

    /**
     * Apply a date range to `$collection` on the given columns. A column passed as null
     * is skipped, so a resource without an updated timestamp still gets the created one.
     *
     * @param array<string, mixed> $filters
     */
    protected function applyDateRangeFilters(
        object $collection,
        array $filters,
        ?string $createdColumn = 'created_at',
        ?string $updatedColumn = 'updated_at',
    ): void {
        foreach ([
            [$createdColumn, 'createdFrom', 'gteq'],
            [$createdColumn, 'createdTo', 'lteq'],
            [$updatedColumn, 'updatedSince', 'gteq'],
        ] as [$column, $key, $operator]) {
            $value = $this->stringFilter($filters, $key);
            if ($column === null || $value === null) {
                continue;
            }
            $collection->addFieldToFilter($column, [
                $operator => $this->normalizeBoundary($value, $operator === 'lteq'),
            ]);
        }
    }

    protected function normalizeBoundary(string $value, bool $isUpperBound): string
    {
        return \Mage::app()->getLocale()->formatDateBoundaryForDb($value, $isUpperBound);
    }
}
