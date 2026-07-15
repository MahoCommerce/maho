<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

namespace Mage\Tax\Api;

use Maho\ApiPlatform\CrudProvider;

/**
 * Tax Rule Provider, extends CrudProvider with a code search filter.
 *
 * Association read-back (customer/product tax classes and rates) is handled by
 * TaxRule::afterLoad(), so it applies to both reads and write responses.
 */
final class TaxRuleProvider extends CrudProvider
{
    protected array $defaultSort = ['priority' => 'ASC', 'position' => 'ASC'];

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        $search = $filters['search'] ?? $filters['q'] ?? null;
        if ($search) {
            $collection->addFieldToFilter('code', ['like' => "%{$search}%"]);
        }
    }
}
