<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
     *
     * @return Mage_Catalog_Model_Resource_Product_Collection|\Maho\Data\Collection
     */
    abstract protected function _getProductCollection();

    /**
     * Portable "ORDER BY" expression that sorts rows to match an explicit id list.
     * Uses ANSI CASE so it works on MySQL, PostgreSQL and SQLite alike (MySQL's FIELD() does not).
     *
     * @param int[] $ids
     */
    protected function _getOrderByIdsExpr(array $ids, string $field = 'e.entity_id'): Maho\Db\Expr
    {
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
        ];
    }

    /**
     * @return int
     */
    public function getProductsCount()
    {
        if (!$this->hasData('products_count')) {
            $this->setData('products_count', self::DEFAULT_PRODUCTS_COUNT);
        }
        return (int) $this->getData('products_count');
    }

    /**
     * @return int
     */
    public function getProductsPerPage()
    {
        if (!$this->hasData('products_per_page')) {
            $this->setData('products_per_page', self::DEFAULT_PRODUCTS_PER_PAGE);
        }
        return (int) $this->getData('products_per_page');
    }

    /**
     * @return bool
     */
    public function showPager()
    {
        if (!$this->hasData('show_pager')) {
            $this->setData('show_pager', self::DEFAULT_SHOW_PAGER);
        }
        return (bool) $this->getData('show_pager');
    }

    /**
     * @return string
     */
    public function getPagerHtml()
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
