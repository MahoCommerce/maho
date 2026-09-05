<?php

/**
 * Frontend widget listing a curated set of products: a category, a SKU list, or both.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Block_Product_Widget_List extends Mage_Catalog_Block_Product_Widget_Abstract
{
    public const DEFAULT_SORT = 'position';

    protected $_pageVarName = 'pl';
    protected $_cacheKeyPrefix = 'CATALOG_PRODUCT_WIDGET_LIST';

    public function getTitle(): string
    {
        return trim((string) $this->getData('title'));
    }

    /**
     * The category chooser stores "category/12"; a hand-typed value is a bare id.
     */
    public function getCategoryId(): ?int
    {
        $value = trim((string) $this->getData('category_id'));
        if (str_starts_with($value, 'category/')) {
            $value = substr($value, strlen('category/'));
        }
        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * @return string[]
     */
    public function getSkus(): array
    {
        $skus = preg_split('/[\s,]+/', (string) $this->getData('skus'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique($skus));
    }

    public function getSortMode(): string
    {
        if (!$this->hasData('sort')) {
            $this->setData('sort', self::DEFAULT_SORT);
        }
        return (string) $this->getData('sort');
    }

    #[\Override]
    public function getCacheKeyInfo()
    {
        return array_merge(parent::getCacheKeyInfo(), [
            (int) $this->getCategoryId(),
            implode(',', $this->getSkus()),
            $this->getSortMode(),
            (int) $this->onlyInStock(),
            $this->getTitle(),
        ]);
    }

    #[\Override]
    protected function _getProductCollection(): Mage_Catalog_Model_Resource_Product_Collection
    {
        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->setVisibility(Mage_Catalog_Model_Product_Visibility::getVisibleInCatalogIds());

        $categoryId = $this->getCategoryId();
        $skus = $this->getSkus();
        if ($categoryId === null && $skus === []) {
            $collection->getSelect()->where('1 = 0');
            return $collection;
        }

        $category = null;
        if ($categoryId !== null) {
            $category = Mage::getModel('catalog/category')->setStoreId(Mage::app()->getStore()->getId())->load($categoryId);
            if (!$category->getId()) {
                $collection->getSelect()->where('1 = 0');
                return $collection;
            }
        }

        $this->_addProductAttributesAndPrices($collection)
            ->addStoreFilter()
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

        if ($this->onlyInStock()) {
            Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);
        }
        if ($category !== null) {
            $collection->addCategoryFilter($category);
        }
        if ($skus !== []) {
            $collection->addAttributeToFilter('sku', ['in' => $skus]);
        }

        $this->_applySort($collection, $skus, $category !== null);
        $collection->setPageSize($this->getProductsCount())->setCurPage(1);

        return $collection;
    }

    /**
     * "position" means the category position when a category is set, else the SKU list order.
     *
     * @param string[] $skus
     */
    protected function _applySort(Mage_Catalog_Model_Resource_Product_Collection $collection, array $skus, bool $hasCategory): void
    {
        match ($this->getSortMode()) {
            'newest' => $collection->addAttributeToSort('created_at', 'desc'),
            'name' => $collection->addAttributeToSort('name', 'asc'),
            'price_asc' => $collection->addAttributeToSort('price', 'asc'),
            'price_desc' => $collection->addAttributeToSort('price', 'desc'),
            'random' => $collection->getSelect()->orderRand(),
            default => $hasCategory
                ? $collection->addAttributeToSort('position', 'asc')
                : $collection->getSelect()->order($this->_getOrderBySkusExpr($collection, $skus)),
        };
    }

    /**
     * Portable "ORDER BY" expression that sorts rows to match the SKU list order.
     *
     * @param string[] $skus
     */
    protected function _getOrderBySkusExpr(Mage_Catalog_Model_Resource_Product_Collection $collection, array $skus): Maho\Db\Expr
    {
        $adapter = $collection->getConnection();
        $cases = '';
        foreach (array_values($skus) as $position => $sku) {
            $cases .= ' WHEN ' . $adapter->quote($sku) . ' THEN ' . $position;
        }
        return new Maho\Db\Expr('CASE e.sku' . $cases . ' END');
    }
}
