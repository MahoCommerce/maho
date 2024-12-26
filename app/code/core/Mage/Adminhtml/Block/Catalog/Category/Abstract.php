<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Category abstract block
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 *
 * @method setRecursionLevel(?int $value)
 */
class Mage_Adminhtml_Block_Catalog_Category_Abstract extends Mage_Adminhtml_Block_Template
{
    public const DEFAULT_RECURSION_LEVEL = 3;

    /**
     * Retrieve current category instance
     *
     * @return Mage_Catalog_Model_Category
     */
    public function getCategory()
    {
        return Mage::registry('category');
    }

    public function getCategoryId()
    {
        if ($this->getCategory()) {
            return $this->getCategory()->getId();
        }
        return Mage_Catalog_Model_Category::TREE_ROOT_ID;
    }

    public function getCategoryName()
    {
        return $this->getCategory()->getName();
    }

    public function getCategoryPath()
    {
        if ($this->getCategory()) {
            return $this->getCategory()->getPath();
        }
        return Mage_Catalog_Model_Category::TREE_ROOT_ID;
    }

    public function hasStoreRootCategory()
    {
        $root = $this->getRoot();
        if ($root && $root->getId()) {
            return true;
        }
        return false;
    }

    public function getStore()
    {
        $storeId = (int) $this->getRequest()->getParam('store');
        return Mage::app()->getStore($storeId);
    }

    /**
     * Return the number of children levels when loading a tree node
     */
    public function getRecursionLevel(): int
    {
        return (int) ($this->getDataByKey('recursion_level') ?? self::DEFAULT_RECURSION_LEVEL);
    }

    /**
     * Get and register category tree root
     *
     * Root node will be store's root category, or Mage_Catalog_Model_Category::TREE_ROOT_ID if no store is requested
     * If $parentNodeCategory is given, call will be forwarded to self::getNode() and root will not be registered
     *
     * @param Mage_Catalog_Model_Category|int|string $parentNodeCategory
     * @param int $recursionLevel how many levels to load
     * @return Varien_Data_Tree_Node|null
     */
    public function getRoot($parentNodeCategory = null, $recursionLevel = null)
    {
        if (!is_null($recursionLevel)) {
            $this->setRecursionLevel($recursionLevel);
        }
        if (!is_null($parentNodeCategory)) {
            if (!$parentNodeCategory instanceof Mage_Catalog_Model_Category) {
                $parentNodeCategory = Mage::getModel('catalog/category')->load($parentNodeCategory);
            }
            if ($parentNodeCategory->getId()) {
                return $this->getNode($parentNodeCategory);
            }
        }
        if ($this->getCategory()) {
            return $this->getRootByIds([$this->getCategory()->getId()]);
        }
        $root = Mage::registry('root');
        if (is_null($root)) {
            $store = $this->getStore();
            if ($store->getId()) {
                $rootId = $store->getRootCategoryId();
                if ($this->getRecursionLevel() !== 0) {
                    $this->setRecursionLevel($this->getRecursionLevel() + 1);
                }
            } else {
                $rootId = Mage_Catalog_Model_Category::TREE_ROOT_ID;
            }

            $tree = Mage::getResourceSingleton('catalog/category_tree')
                ->load(null, $this->getRecursionLevel());

            $tree->addCollectionData($this->getCategoryCollection());
            $root = $tree->getNodeById($rootId);

            if ($root) {
                if ($rootId == Mage_Catalog_Model_Category::TREE_ROOT_ID) {
                    $root->setName(Mage::helper('catalog')->__('Root'));
                } else {
                    $root->setIsVisible(true);
                }
            }

            Mage::register('root', $root);
        }

        return $root;
    }

    /**
     * Get and register category tree root by specified category IDs
     *
     * IDs can be arbitrary set of any categories ids.
     * Tree with minimal required nodes (all parents and neighbours) will be built.
     * If ids are empty, default tree with depth = $recursionLevel will be returned.
     *
     * @param array $ids list of category ids
     * @param int $recursionLevel how many levels to load
     * @return Varien_Data_Tree_Node|null
     */
    public function getRootByIds($ids, $recursionLevel = null)
    {
        if (!is_null($recursionLevel)) {
            $this->setRecursionLevel($recursionLevel);
        }
        if (!is_array($ids) || empty($ids)) {
            return $this->getRoot();
        }
        $root = Mage::registry('root');
        if (is_null($root)) {
            $store = $this->getStore();
            if ($store->getId()) {
                $rootId = $store->getRootCategoryId();
                if ($this->getRecursionLevel() !== 0) {
                    $this->setRecursionLevel($this->getRecursionLevel() + 1);
                }
            } else {
                $rootId = Mage_Catalog_Model_Category::TREE_ROOT_ID;
            }

            $tree = Mage::getResourceSingleton('catalog/category_tree')
                ->loadByIds($ids, false, false, $this->getRecursionLevel());

            $tree->addCollectionData($this->getCategoryCollection());
            $root = $tree->getNodeById($rootId);

            if ($root) {
                if ($rootId == Mage_Catalog_Model_Category::TREE_ROOT_ID) {
                    $root->setName(Mage::helper('catalog')->__('Root'));
                } else {
                    $root->setIsVisible(true);
                }
            }

            Mage::register('root', $root);
        }
        return $root;
    }

    /**
     * Get category tree with specified category as the root
     *
     * @param Mage_Catalog_Model_Category $parentNodeCategory
     * @param int $recursionLevel how many levels to load
     * @return Varien_Data_Tree_Node|null
     */
    public function getNode($parentNodeCategory, $recursionLevel = null)
    {
        if (!is_null($recursionLevel)) {
            $this->setRecursionLevel($recursionLevel);
        }

        $tree = Mage::getResourceModel('catalog/category_tree');

        $nodeId = $parentNodeCategory->getId();
        $node = $tree->loadNode($nodeId);
        $node->loadChildren($this->getRecursionLevel());

        $tree->addCollectionData($this->getCategoryCollection());

        if ($node) {
            if ($nodeId == Mage_Catalog_Model_Category::TREE_ROOT_ID) {
                $node->setName(Mage::helper('catalog')->__('Root'));
            } else {
                $node->setIsVisible(true);
            }
        }

        return $node;
    }

    public function getSaveUrl(array $args = [])
    {
        $params = ['_current' => true];
        $params = array_merge($params, $args);
        return $this->getUrl('*/*/save', $params);
    }

    public function getEditUrl()
    {
        return $this->getUrl('*/catalog_category/edit', ['_current' => true, 'store' => null, '_query' => false, 'id' => null, 'parent' => null]);
    }

    /**
     * Return ids of root categories as array
     *
     * @return array
     */
    public function getRootIds()
    {
        $ids = $this->getData('root_ids');
        if (is_null($ids)) {
            $ids = [];
            foreach (Mage::app()->getGroups() as $store) {
                $ids[] = $store->getRootCategoryId();
            }
            $this->setData('root_ids', $ids);
        }
        return $ids;
    }
}
