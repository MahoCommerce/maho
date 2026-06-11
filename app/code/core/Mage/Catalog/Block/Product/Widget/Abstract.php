<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

/**
 * Shared base for product-list widgets that render a paginated product collection
 * (New Products, Bestsellers, On Sale, ...).
 */
abstract class Mage_Catalog_Block_Product_Widget_Abstract extends Mage_Catalog_Block_Product_Abstract implements Mage_Widget_Block_Interface
{
    public const DEFAULT_SHOW_PAGER = false;
    public const DEFAULT_PRODUCTS_COUNT = 5;
    public const DEFAULT_PRODUCTS_PER_PAGE = 5;

    /**
     * Request parameter name for the pager page number. Override per widget to avoid collisions.
     *
     * @var string
     */
    protected $_pageVarName = 'p';

    /**
     * Prefix that namespaces this widget's block-cache key.
     *
     * @var string
     */
    protected $_cacheKeyPrefix = 'CATALOG_PRODUCT_WIDGET';

    /**
     * @var Mage_Catalog_Block_Product_Widget_Html_Pager|null
     */
    protected $_pager;

    #[\Override]
    protected function _construct()
    {
        parent::_construct();

        $this->addColumnCountLayoutDepend('empty', 6)
            ->addColumnCountLayoutDepend('one_column', 5)
            ->addColumnCountLayoutDepend('two_columns_left', 4)
            ->addColumnCountLayoutDepend('two_columns_right', 4)
            ->addColumnCountLayoutDepend('three_columns', 3);

        $this->addData(['cache_lifetime' => 86400]);
        $this->addCacheTag(Mage_Catalog_Model_Product::CACHE_TAG);
        $this->addPriceBlockType('bundle', 'bundle/catalog_product_price', 'bundle/catalog/product/price.phtml');
    }

    /**
     * Prepare the product collection rendered by the widget templates.
     */
    abstract protected function _getProductCollection(): Mage_Catalog_Model_Resource_Product_Collection;

    /**
     * Portable "ORDER BY" expression that sorts rows to match an explicit id list.
     * Uses ANSI CASE so it works on MySQL, PostgreSQL and SQLite alike (MySQL's FIELD() does not).
     *
     * @param int[] $ids
     */
    protected function _getOrderByIdsExpr(array $ids, string $field = 'e.entity_id'): Maho\Db\Expr
    {
        if (empty($ids)) {
            return new Maho\Db\Expr('NULL');
        }
        $cases = '';
        foreach (array_values($ids) as $position => $id) {
            $cases .= ' WHEN ' . (int) $id . ' THEN ' . $position;
        }
        return new Maho\Db\Expr('CASE ' . $field . $cases . ' END');
    }

    #[\Override]
    protected function _beforeToHtml()
    {
        $this->setProductCollection($this->_getProductCollection());
        return parent::_beforeToHtml();
    }

    #[\Override]
    public function getCacheKeyInfo()
    {
        return [
            $this->_cacheKeyPrefix,
            Mage::app()->getStore()->getId(),
            Mage::getDesign()->getPackageName(),
            Mage::getDesign()->getTheme('template'),
            Mage::getSingleton('customer/session')->getCustomerGroupId(),
            'template' => $this->getTemplate(),
            $this->getProductsCount(),
            Mage::app()->getStore()->getCurrentCurrencyCode(),
            (int) $this->getRequest()->getParam($this->_pageVarName),
            // Refresh daily: these lists are time-sensitive (rolling periods, daily catalog-rule reindex).
            Mage::app()->getLocale()->utcToStore()->format(Mage_Core_Model_Locale::DATE_FORMAT),
        ];
    }

    /**
     * Whether to restrict the list to in-stock products. Defaults to true.
     */
    public function onlyInStock(): bool
    {
        if (!$this->hasData('only_in_stock')) {
            $this->setData('only_in_stock', true);
        }
        return (bool) $this->getData('only_in_stock');
    }

    /**
     * Build a storefront-safe product collection limited to the given ids: visible in catalog,
     * enabled, in the current store, and (when onlyInStock) in stock. Callers add their own
     * ordering and pagination. Returns an empty (never null) collection for an empty id list.
     *
     * @param int[] $productIds
     */
    protected function _prepareStorefrontCollection(array $productIds): Mage_Catalog_Model_Resource_Product_Collection
    {
        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->setVisibility(Mage_Catalog_Model_Product_Visibility::getVisibleInCatalogIds());

        if (empty($productIds)) {
            $collection->getSelect()->where('1 = 0');
            return $collection;
        }

        $this->_addProductAttributesAndPrices($collection)
            ->addStoreFilter()
            ->addIdFilter($productIds)
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

        if ($this->onlyInStock()) {
            Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);
        }

        return $collection;
    }

    public function getProductsCount(): int
    {
        if (!$this->hasData('products_count')) {
            $this->setData('products_count', self::DEFAULT_PRODUCTS_COUNT);
        }
        return (int) $this->getData('products_count');
    }

    public function getProductsPerPage(): int
    {
        if (!$this->hasData('products_per_page')) {
            $this->setData('products_per_page', self::DEFAULT_PRODUCTS_PER_PAGE);
        }
        return (int) $this->getData('products_per_page');
    }

    public function showPager(): bool
    {
        if (!$this->hasData('show_pager')) {
            $this->setData('show_pager', self::DEFAULT_SHOW_PAGER);
        }
        return (bool) $this->getData('show_pager');
    }

    public function getPagerHtml(): string
    {
        if (!$this->showPager()) {
            return '';
        }
        if (!$this->_pager) {
            /** @var Mage_Catalog_Block_Product_Widget_Html_Pager $pager */
            $pager = $this->getLayout()
                ->createBlock('catalog/product_widget_html_pager', $this->getNameInLayout() . '.pager');
            $this->_pager = $pager;
            $this->_pager->setUseContainer(true)
                ->setShowAmounts(true)
                ->setShowPerPage(false)
                ->setPageVarName($this->_pageVarName)
                ->setLimit($this->getProductsPerPage())
                ->setTotalLimit($this->getProductsCount())
                ->setCollection($this->getProductCollection());
        }
        return $this->_pager->toHtml();
    }
}
