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
 * Tax Class Provider, extends CrudProvider with class-type and name filters.
 *
 * All field mapping and DTO construction is handled by CrudResource/CrudProvider.
 */
final class TaxClassProvider extends CrudProvider
{
    protected array $defaultSort = ['class_name' => 'ASC'];

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        if (!empty($filters['classType'])) {
            $collection->addFieldToFilter('class_type', $filters['classType']);
        }

        $search = $filters['search'] ?? $filters['q'] ?? null;
        if ($search) {
            $collection->addFieldToFilter('class_name', ['like' => "%{$search}%"]);
        }
    }
}
