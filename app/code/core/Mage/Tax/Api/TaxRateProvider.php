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
 * Tax Rate Provider, extends CrudProvider with code/country filters.
 *
 * All field mapping and DTO construction is handled by CrudResource/CrudProvider.
 */
final class TaxRateProvider extends CrudProvider
{
    protected array $defaultSort = ['code' => 'ASC'];

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        if (!empty($filters['taxCountryId'])) {
            $collection->addFieldToFilter('tax_country_id', $filters['taxCountryId']);
        }

        $search = $filters['search'] ?? $filters['q'] ?? null;
        if ($search) {
            $collection->addFieldToFilter('code', ['like' => "%{$search}%"]);
        }
    }
}
