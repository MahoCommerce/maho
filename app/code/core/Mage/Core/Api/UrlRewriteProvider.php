<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use Maho\ApiPlatform\CrudProvider;

final class UrlRewriteProvider extends CrudProvider
{
    #[\Override]
    protected array $defaultSort = ['url_rewrite_id' => 'DESC'];

    /**
     * Rewrites are admin data for every store view, so the current-store filter
     * of the base provider does not apply. A store-restricted token still sees
     * only its own store views.
     */
    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        $allowed = $this->allowedStoreIds();
        if ($allowed !== null) {
            $collection->addStoreFilter($allowed, false);
        }

        $storeId = $this->intFilter($filters, 'storeId');
        if ($storeId !== null) {
            $collection->addFieldToFilter('store_id', $storeId);
        }

        $requestPath = $this->stringFilter($filters, 'requestPath');
        if ($requestPath !== null) {
            $collection->addFieldToFilter('request_path', $requestPath);
        }

        $search = $this->stringFilter($filters, 'search');
        if ($search !== null) {
            $collection->addFieldToFilter(
                ['request_path', 'target_path'],
                [
                    ['like' => "%{$search}%"],
                    ['like' => "%{$search}%"],
                ],
            );
        }

        $productId = $this->intFilter($filters, 'productId');
        if ($productId !== null) {
            $collection->addFieldToFilter('product_id', $productId);
        }

        $categoryId = $this->intFilter($filters, 'categoryId');
        if ($categoryId !== null) {
            $collection->addFieldToFilter('category_id', $categoryId);
        }

        $isSystem = $this->booleanFilter($filters, 'isSystem');
        if ($isSystem !== null) {
            $collection->addFieldToFilter('is_system', $isSystem ? 1 : 0);
        }
    }
}
