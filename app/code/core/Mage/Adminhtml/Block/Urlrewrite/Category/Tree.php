<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Urlrewrite_Category_Tree extends Mage_Adminhtml_Block_Catalog_Category_Abstract
{
    /**
     * List of allowed category ids
     *
     * @var array|null
     */
    protected $_allowedCategoryIds = null;

    /**
     * Set custom template for the block
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('urlrewrite/categories.phtml');
    }

    /**
     * Return array with category IDs which the product is assigned to
     *
     * @return array
     */
    protected function getCategoryIds()
    {
        $productId = Mage::app()->getRequest()->getParam('product');
        if ($productId && $this->_allowedCategoryIds === null) {
            $this->_allowedCategoryIds = Mage::getModel('catalog/product')->setId($productId)->getCategoryIds();
        }
        return $this->_allowedCategoryIds;
    }

    /**
     * Get categories tree as recursive array
     *
     * @param int $parentId
     * @param bool $asJson
     * @param int $recursionLevel
     * @return ($asJson is true ? string : array)
     */
    public function getTreeArray($parentId = null, $asJson = false, $recursionLevel = null)
    {
        if ($recursionLevel !== null) {
            $this->setRecursionLevel($recursionLevel);
        }

        $result = $this->getTree($parentId);

        if ($asJson) {
            return Mage::helper('core')->jsonEncode($result);
        }

        return $result;
    }

    #[\Override]
    public function getRoot($parentNodeCategory = null, $recursionLevel = null)
    {
        if ($parentNodeCategory === null && $this->getCategoryIds()) {
            return $this->getRootByIds($this->getCategoryIds(), $recursionLevel);
        }
        return parent::getRoot($parentNodeCategory, $recursionLevel);
    }

    #[\Override]
    protected function _getNodeJson($node, $level = 1)
    {
        $item = parent::_getNodeJson($node, $level);
        $item['cls'] = str_replace('no-active-category', 'active-category', $item['cls']);

        $categoryIds = $this->getCategoryIds();
        if ($categoryIds !== null && !in_array($item['id'], $categoryIds)) {
            $item['disabled'] = true;
        }
        if ($categoryIds === null && $node->getLevel() < 3) {
            $item['expanded'] = true;
        }

        return $item;
    }

    #[\Override]
    protected function _isParentSelectedCategory($node)
    {
        if ($this->getCategoryIds() !== null) {
            $children = array_keys($node->getAllChildNodes());
            return !empty(array_intersect($children, $this->getCategoryIds()));
        }
        return false;
    }

    /**
     * Returns URL for loading tree
     *
     * @param null $expanded deprecated
     * @return string
     */
    #[\Override]
    public function getLoadTreeUrl($expanded = null)
    {
        return $this->getUrl('*/*/categoriesJson', ['_current' => ['product']]);
    }
}
