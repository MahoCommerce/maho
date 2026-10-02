<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use Maho\ApiPlatform\CrudProvider;

final class DesignChangeProvider extends CrudProvider
{
    #[\Override]
    protected array $defaultSort = ['store_id' => 'ASC', 'date_from' => 'ASC'];

    /**
     * Design changes are admin data for every store view, so the current-store
     * filter of the base provider does not apply. A store-restricted token still
     * sees only its own store views.
     */
    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        $allowed = $this->allowedStoreIds();
        if ($allowed !== null) {
            $collection->addStoreFilter($allowed);
        }

        $storeId = $this->intFilter($filters, 'storeId');
        if ($storeId !== null) {
            $collection->addFieldToFilter('store_id', $storeId);
        }

        $design = $this->stringFilter($filters, 'design');
        if ($design !== null) {
            $collection->addFieldToFilter('design', $design);
        }
    }
}
