<?php

/**
 * Reads the products of a category for the tile view of the admin: the order of the storefront, the filters
 * and the data of each tile.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Category_Merchandiser
{
    public const POSITION_SORT = 'position';

    /**
     * Retrieve the sort that the storefront uses for the category in the store of the category.
     * The sort of the category, or else the sort of the store configuration, as Mage_Catalog_Model_Category says.
     */
    public function getSort(Mage_Catalog_Model_Category $category): string
    {
        return $category->getDefaultSortBy();
    }

    /**
     * The merchant sets the order by hand only when the category sorts by position.
     */
    public function isManual(Mage_Catalog_Model_Category $category): bool
    {
        return $this->getSort($category) === self::POSITION_SORT;
    }

    public function getSortLabel(Mage_Catalog_Model_Category $category): string
    {
        $sort = $this->getSort($category);
        return $category->getAvailableSortByOptions()[$sort]
            ?? Mage::getSingleton('catalog/config')->getAttributeUsedForSortByArray()[$sort]
            ?? $sort;
    }

    /**
     * Retrieve the number of products that one page of the storefront shows.
     */
    public function getPerPage(Mage_Catalog_Model_Category $category): int
    {
        $storeId = $category->getStoreId();
        $path = str_starts_with((string) Mage::getStoreConfig('catalog/frontend/list_mode', $storeId), 'list')
            ? 'catalog/frontend/list_per_page'
            : 'catalog/frontend/grid_per_page';
        return max(1, Mage::getStoreConfigAsInt($path, $storeId));
    }

    /**
     * Retrieve the IDs of the products of the category in the order of the storefront, when the sort is not the
     * position. Out of stock products go last when the category asks for it.
     *
     * @return list<int>
     */
    public function getStorefrontOrder(Mage_Catalog_Model_Category $category): array
    {
        $sort = $this->getSort($category);
        $collection = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId((int) $category->getStoreId())
            ->joinField(
                'category_position',
                'catalog/category_product',
                'position',
                'product_id = entity_id',
                'category_id = ' . (int) $category->getId(),
            );
        if ($category->getOutOfStockLast()) {
            $collection->addOutOfStockLastSort();
        }
        $direction = in_array($sort, Mage_Catalog_Model_Config::DESCENDING_SORTS, true) ? 'desc' : 'asc';
        $collection->addAttributeToSort($sort, $direction)->load();

        return array_map(intval(...), $collection->getColumnValues('entity_id'));
    }

    /**
     * Retrieve the IDs of $ids that match the filters, in the order of $ids.
     *
     * @param list<int> $ids
     * @return list<int>
     */
    public function filter(array $ids, int $storeId, string $search = '', bool $outOfStockOnly = false): array
    {
        if ($ids === [] || ($search === '' && !$outOfStockOnly)) {
            return $ids;
        }

        $collection = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId($storeId)
            ->addIdFilter($ids);
        if ($search !== '') {
            $like = Mage::getResourceHelper('core')->addLikeEscape($search, ['position' => 'any']);
            $collection->addAttributeToFilter([
                ['attribute' => 'name', 'like' => $like],
                ['attribute' => 'sku', 'like' => $like],
            ], null, 'left');
        }
        $matches = array_flip(array_map(intval(...), $collection->getAllIds()));

        if ($outOfStockOnly) {
            $statuses = $this->getStockStatuses($ids, $storeId);
            $matches = array_filter(
                $matches,
                static fn(int $productId): bool => !($statuses[$productId] ?? false),
                ARRAY_FILTER_USE_KEY,
            );
        }

        return array_values(array_filter($ids, static fn(int $productId): bool => isset($matches[$productId])));
    }

    /**
     * Retrieve the products of the category that are not in $excluded and match $search, up to $limit.
     *
     * @param list<int> $excluded
     * @return list<int>
     */
    public function search(string $search, array $excluded, int $storeId, int $limit): array
    {
        $collection = Mage::getResourceModel('catalog/product_collection')->setStoreId($storeId);
        $like = Mage::getResourceHelper('core')->addLikeEscape($search, ['position' => 'any']);
        $collection->addAttributeToFilter([
            ['attribute' => 'name', 'like' => $like],
            ['attribute' => 'sku', 'like' => $like],
        ], null, 'left');
        if ($excluded !== []) {
            $collection->addFieldToFilter('entity_id', ['nin' => $excluded]);
        }
        $collection->addAttributeToSort('name')->addAttributeToSort('entity_id')->setPageSize($limit)->load();

        return array_map(intval(...), $collection->getColumnValues('entity_id'));
    }

    /**
     * Retrieve the data of the tile of each product in $ids, in the order of $ids.
     *
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    public function getTiles(Mage_Catalog_Model_Category $category, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $storeId = (int) $category->getStoreId();
        $collection = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId($storeId)
            ->addIdFilter($ids)
            ->addAttributeToSelect(['name', 'sku', 'price', 'image'])
            ->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'left', $storeId)
            ->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'left', $storeId)
            ->load();

        $statuses = $this->getStockStatuses($ids, $storeId);
        $sales = $this->getSales($ids, $storeId);
        $store = Mage::app()->getStore($storeId);
        $currency = $store->getBaseCurrency();
        $visibilities = Mage_Catalog_Model_Product_Visibility::getOptionArray();
        $byRule = (bool) $category->getData('is_dynamic');
        $image = Mage::helper('catalog/image');

        $tiles = [];
        foreach ($collection as $product) {
            /** @var Mage_Catalog_Model_Product $product */
            $productId = (int) $product->getId();
            $isEnabled = (int) $product->getData('status') === Mage_Catalog_Model_Product_Status::STATUS_ENABLED;
            $tiles[$productId] = [
                'id' => $productId,
                'name' => (string) $product->getData('name'),
                'sku' => (string) $product->getData('sku'),
                'price' => $currency->formatTxt((float) $product->getData('price')),
                'image' => (string) $image->init($product, 'image')->resize(320),
                'is_enabled' => $isEnabled,
                'visibility' => (string) ($visibilities[(int) $product->getData('visibility')] ?? ''),
                'is_visible' => (int) $product->getData('visibility') === Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                'in_stock' => $statuses[$productId] ?? false,
                'qty_sold' => (float) ($sales[$productId]['qty_ordered'] ?? 0),
                'revenue' => $currency->formatTxt((float) ($sales[$productId]['revenue'] ?? 0)),
                'by_rule' => $byRule,
            ];
        }

        $ordered = [];
        foreach ($ids as $productId) {
            if (isset($tiles[$productId])) {
                $ordered[] = $tiles[$productId];
            }
        }
        return $ordered;
    }

    /**
     * Retrieve the time of the last refresh of the statistics of a report in UTC, or null when it never ran.
     *
     * @param 'bestsellers'|'viewed' $reportCode
     */
    public function getUpdatedAt(string $reportCode): ?string
    {
        if (!Mage::helper('catalog')->isModuleEnabled('Mage_Reports')) {
            return null;
        }
        return Mage::getModel('reports/statistics')->getUpdatedAt($reportCode);
    }

    /**
     * @param list<int> $ids
     * @return array<int, bool> true when the product is in stock
     */
    protected function getStockStatuses(array $ids, int $storeId): array
    {
        $websiteId = (int) Mage::app()->getStore($storeId)->getWebsiteId();
        if ($websiteId === 0) {
            $websiteId = (int) Mage::app()->getDefaultStoreView()?->getWebsiteId();
        }
        $statuses = [];
        foreach (Mage::getResourceSingleton('cataloginventory/stock_status')->getProductStatus($ids, $websiteId) as $productId => $status) {
            $statuses[(int) $productId] = (int) $status === Mage_CatalogInventory_Model_Stock_Status::STATUS_IN_STOCK;
        }
        return $statuses;
    }

    /**
     * @param list<int> $ids
     * @return array<int, array{qty_ordered: string, revenue: string}>
     */
    protected function getSales(array $ids, int $storeId): array
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()
            ->from($resource->getTableName('sales/bestsellers_aggregated_rolling'), ['product_id', 'qty_ordered', 'revenue'])
            ->where('store_id = ?', $storeId)
            ->where('product_id IN (?)', $ids);

        return $adapter->fetchAssoc($select);
    }
}
