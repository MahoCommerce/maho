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
            if ($column === null || ($filters[$key] ?? '') === '') {
                continue;
            }
            $collection->addFieldToFilter($column, [
                $operator => $this->normalizeBoundary((string) $filters[$key], $operator === 'lteq'),
            ]);
        }
    }

    /**
     * Normalize a client-supplied bound to a UTC `Y-m-d H:i:s` string.
     *
     * A date with no time is the whole day, so an upper bound has to become 23:59:59:
     * `createdTo=2026-07-29` compared against a bare `2026-07-29` would exclude every
     * order placed that day, which is precisely the range a caller asking for "today"
     * means to include.
     */
    private function normalizeBoundary(string $value, bool $isUpperBound): string
    {
        $locale = \Mage::app()->getLocale();
        $dateOnly = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) === 1;

        if ($dateOnly && $isUpperBound) {
            return $locale->formatDateForDb(trim($value) . ' 23:59:59');
        }

        return $locale->formatDateForDb($value);
    }
}
