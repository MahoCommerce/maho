<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api;

use Maho\ApiPlatform\CrudProvider;

/**
 * Customer Group Provider, extends CrudProvider with a code search filter.
 *
 * All field mapping and DTO construction is handled by CrudResource/CrudProvider.
 */
final class CustomerGroupProvider extends CrudProvider
{
    protected array $defaultSort = ['customer_group_code' => 'ASC'];

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        $search = $filters['search'] ?? $filters['q'] ?? null;
        if ($search) {
            $collection->addFieldToFilter('customer_group_code', ['like' => "%{$search}%"]);
        }
    }
}
