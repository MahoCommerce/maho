<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Product categories tab
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Categories extends Mage_Adminhtml_Block_Catalog_Category_Abstract
{
    /**
     * Cache for selected tree nodes
     *
     * list<Varien_Data_Tree_Node>
     */
    protected $_selectedNodes = null;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('catalog/product/edit/categories.phtml');
    }

    /**
     * Retrieve currently edited product
     *
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct()
    {
        return Mage::registry('current_product');
    }

    /**
     * Checks when this block is readonly
     *
     * @return bool
     */
    public function isReadonly()
    {
        return $this->getProduct()->getCategoriesReadonly();
    }

    /**
     * Return array with category IDs which the product is assigned to
     *
     * @return array
     */
    protected function getCategoryIds()
    {
        return $this->getProduct()->getCategoryIds();
    }

    /**
     * Forms string out of getCategoryIds()
     *
     * @return string
     */
    public function getIdsString()
    {
        return implode(',', $this->getCategoryIds());
    }

    /**
     * Returns root node and sets 'checked' flag (if necessary)
     *
     * @return Varien_Data_Tree_Node
     */
    public function getRootNode()
    {
        $root = $this->getRoot();
        if ($root && in_array($root->getId(), $this->getCategoryIds())) {
            $root->setChecked(true);
        }
        return $root;
    }

    #[\Override]
    public function getRoot($parentNodeCategory = null, $recursionLevel = null)
    {
        if ($parentNodeCategory === null && $this->getCategoryIds()) {
            return $this->getRootByIds($this->getCategoryIds(), $recursionLevel);
        } else {
            return parent::getRoot($parentNodeCategory, $recursionLevel);
        }
    }

    #[\Override]
    protected function _getNodeJson($node, $level = 1)
    {
        $item = parent::_getNodeJson($node, $level);
        if (in_array($node->getId(), $this->getCategoryIds())) {
            $item['checked'] = true;
        }
        if ($this->isReadonly()) {
            $item['disabled'] = true;
        }
        return $item;
    }

    #[\Override]
    protected function _isParentSelectedCategory($node)
    {
        foreach ($this->_getSelectedNodes() as $selected) {
            if ($selected) {
                $pathIds = explode('/', $selected->getPathId());
                if (in_array($node->getId(), $pathIds)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns array with nodes those are selected (contain current product)
     *
     * @return array
     */
    protected function _getSelectedNodes()
    {
        if ($this->_selectedNodes === null) {
            $this->_selectedNodes = [];
            $root = $this->getRoot();
            foreach ($this->getCategoryIds() as $categoryId) {
                if ($root) {
                    $this->_selectedNodes[] = $root->getTree()->getNodeById($categoryId);
                }
            }
        }

        return $this->_selectedNodes;
    }

    /**
     * Returns JSON-encoded array of category children
     *
     * @param int $categoryId
     * @return string
     * @deprecated use self::getTreeJson()
     */
    public function getCategoryChildrenJson($categoryId)
    {
        return $this->getTreeJson($categoryId);
    }

    /**
     * Return distinct path ids of selected categories
     *
     * @param mixed $rootId Root category Id for context
     * @return array
     * @deprecated Mage_Catalog_Model_Resource_Category_Tree::loadByIds() already loads parent ids
     */
    public function getSelectedCategoriesPathIds($rootId = false)
    {
        $ids = [];
        $categoryIds = $this->getCategoryIds();
        if (empty($categoryIds)) {
            return [];
        }
        $collection = Mage::getResourceModel('catalog/category_collection');

        if ($rootId) {
            $collection->addFieldToFilter([
                ['attribute' => 'parent_id', 'eq' => $rootId],
                ['attribute' => 'entity_id', 'in' => $categoryIds],
            ]);
        } else {
            $collection->addFieldToFilter('entity_id', ['in' => $categoryIds]);
        }

        foreach ($collection as $item) {
            if ($rootId && !in_array($rootId, $item->getPathIds())) {
                continue;
            }
            foreach ($item->getPathIds() as $id) {
                if (!in_array($id, $ids)) {
                    $ids[] = $id;
                }
            }
        }
        return $ids;
    }
}
