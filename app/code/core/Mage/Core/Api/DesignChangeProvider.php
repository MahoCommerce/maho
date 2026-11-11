<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\Resource;

final class DesignChangeProvider extends CrudProvider
{
    #[\Override]
    protected array $defaultSort = ['store_id' => 'ASC', 'date_from' => 'ASC'];

    #[\Override]
    protected function provideItem(int|string $id): ?Resource
    {
        try {
            return $this->toDto(\Mage::getService('core/design')->getById((int) $id, $this->allowedStoreIds()));
        } catch (\Mage_Core_Exception_NoSuchEntity) {
            return null;
        }
    }

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
