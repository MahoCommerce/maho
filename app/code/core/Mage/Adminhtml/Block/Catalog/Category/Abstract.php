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

/**
 * Category tree abstract block
 *
 * @package    Mage_Adminhtml
 *
 * @method setRecursionLevel(?int $value)
 */
class Mage_Adminhtml_Block_Catalog_Category_Abstract extends Mage_Adminhtml_Block_Template
{
    /**
     * Default number of children levels when loading a tree node
     */
    public const DEFAULT_RECURSION_LEVEL = 3;

    /**
     * Whether to load product count when calling self::getCategoryCollection()
     *
     * @var bool
     */
    protected $_withProductCount = true;

    /**
     * Return the number of children levels when loading a tree node
     */
    public function getRecursionLevel(): int
    {
        return (int) ($this->getDataByKey('recursion_level') ?? self::DEFAULT_RECURSION_LEVEL);
    }

    /**
     * Retrieve current category instance
     *
     * @return Mage_Catalog_Model_Category
     */
    public function getCategory()
    {
        return Mage::registry('category');
    }

    /**
     * Retrieve current category's ID, or Mage_Catalog_Model_Category::TREE_ROOT_ID
     *
     * @return ?int
     */
    public function getCategoryId()
    {
        if ($this->getCategory()) {
            return $this->getCategory()->getId()
                ? (int) $this->getCategory()->getId()
                : null;
        }
        return Mage_Catalog_Model_Category::TREE_ROOT_ID;
    }

    /**
     * Retrieve current category's name
     *
     * @return ?string
     */
    public function getCategoryName()
    {
        return $this->getCategory()->getName();
    }

    /**
     * Retrieve current category's path
     *
     * @return ?string
     */
    public function getCategoryPath()
    {
        if ($this->getCategory()) {
            return $this->getCategory()->getPath();
        }
        return (string) Mage_Catalog_Model_Category::TREE_ROOT_ID;
    }

    /**
     * Return default store ID
     *
     * @return int
     */
    protected function _getDefaultStoreId()
    {
        return Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;
    }

    /**
     * Return current store requested in URL
     *
     * @return Mage_Core_Model_Store
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getStore()
    {
        $storeId = (int) $this->getRequest()->getParam('store', $this->_getDefaultStoreId());
        return Mage::app()->getStore($storeId);
    }

    /**
     * Check if store has a root category
     *
     * @return bool
     */
    public function hasStoreRootCategory()
    {
        $root = $this->getRoot();
        if ($root && $root->getId()) {
            return true;
        }
        return false;
    }

    /**
     * Return ids of root categories as array
     *
     * @return list<int>
     */
    public function getRootIds()
    {
        if (!$this->hasData('root_ids')) {
            $this->setData('root_ids', Mage::getResourceModel('catalog/category')->getRootIds());
        }
        return $this->getDataByKey('root_ids');
    }

    /**
     * Get and register category tree root
     *
     * Root node will be store's root category, or Mage_Catalog_Model_Category::TREE_ROOT_ID if no store is requested
     * If $parentNodeCategory is given, call will be forwarded to self::getNode() and root will not be registered
     *
     * @param Mage_Catalog_Model_Category|int|string $parentNodeCategory
     * @param int $recursionLevel how many levels to load
     * @return \Maho\Data\Tree\Node|null
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
            return $this->getRootByIds($this->getCategory()->getPathIds());
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
     * @return \Maho\Data\Tree\Node|null
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
     * @return \Maho\Data\Tree\Node|null
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

    /**
     * Load category collection for adding data to tree nodes
     *
     * @return Mage_Catalog_Model_Resource_Category_Collection
     */
    public function getCategoryCollection()
    {
        $collection = $this->getData('category_collection');
        if (is_null($collection)) {
            $store = $this->getStore();

            /** @var Mage_Catalog_Model_Resource_Category_Collection $collection */
            $collection = Mage::getModel('catalog/category')->getCollection();

            $collection->addAttributeToSelect('name')
                ->addAttributeToSelect('is_active')
                ->setProductStoreId($this->getStore()->getId())
                ->setLoadProductCount($this->_withProductCount)
                ->setStoreId($this->getStore()->getId());

            $this->setData('category_collection', $collection);
        }
        return $collection;
    }

    /**
     * Get category children as array
     *
     * @param Mage_Catalog_Model_Category|int|string $parentNodeCategory
     * @return array
     */
    public function getTree($parentNodeCategory = null)
    {
        $rootArray = $this->_getNodeJson($this->getRoot($parentNodeCategory));
        return $rootArray['children'] ?? [];
    }

    /**
     * Get category children as JSON
     *
     * @param Mage_Catalog_Model_Category|int|string $parentNodeCategory
     * @return string
     */
    public function getTreeJson($parentNodeCategory = null)
    {
        $rootArray = $this->_getNodeJson($this->getRoot($parentNodeCategory));
        return Mage::helper('core')->jsonEncode($rootArray['children'] ?? []);
    }

    /**
     * Get JSON of a tree node or an associative array
     */
    public function getNodeJson(\Maho\Data\Tree\Node|array $node, int $level = 0): array
    {
        return $this->_getNodeJson($node, $level);
    }

    /**
     * Get JSON of a tree node or an associative array
     *
     * @param \Maho\Data\Tree\Node|array $node
     * @param int $level
     * @return array
     */
    protected function _getNodeJson($node, $level = 0)
    {
        // create a node from data array
        if (is_array($node)) {
            $node = new \Maho\Data\Tree\Node($node, 'entity_id', new \Maho\Data\Tree());
        }

        $item = [
            'id'    => (int) $node->getId(),
            'text'  => $this->buildNodeName($node),
            'type'  => 'folder',
            'cls'   => 'folder',
            'store' => (int) $this->getStore()->getId(),
            'path'  => $node->getData('path'),
        ];

        $item['cls'] .= $node->getIsActive() ? ' active-category' : ' no-active-category';

        if ($node->getChildrenCount() == 0 || $node->hasChildren()) {
            $item['children'] = [];
        }

        foreach ($node->getChildren() as $child) {
            $item['children'][] = $this->_getNodeJson($child, $level + 1);
        }

        if ($this->getCategory() && $this->getCategoryId() === (int) $node->getId()) {
            $item['checked'] = true;
        } elseif ($node->getChecked()) {
            $item['checked'] = true;
        }

        $isParent = $this->_isParentSelectedCategory($node);
        if ($isParent || $node->getLevel() < 2) {
            $item['expanded'] = true;
        }

        return $item;
    }

    /**
     * Get category name
     *
     * @param \Maho\DataObject $node
     * @return string
     */
    public function buildNodeName($node)
    {
        $result = $this->escapeHtml($node->getName());
        if ($this->_withProductCount) {
            $result .= " ({$node->getProductCount()})";
        }
        return $result;
    }

    /**
     * Check if the node contains children categories that are selected
     *
     * @param \Maho\DataObject $node
     * @return bool
     */
    protected function _isParentSelectedCategory($node)
    {
        if ($node && $this->getCategory()) {
            $pathIds = $this->getCategory()->getPathIds();
            if (in_array($node->getId(), $pathIds)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns URL for loading tree
     *
     * @param ?bool $expanded
     * @return string
     */
    public function getLoadTreeUrl($expanded = null)
    {
        return $this->getUrl('*/*/categoriesJson', [
            'expand_all' => $expanded,
        ]);
    }

    /**
     * @return string
     */
    public function getSaveUrl(array $args = [])
    {
        return $this->getUrl('*/*/save', [
            '_current' => true, '_query' => false, ...$args,
        ]);
    }

    /**
     * @return string
     */
    public function getEditUrl()
    {
        return $this->getUrl('*/catalog_category/edit');
    }
}
