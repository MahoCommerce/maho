<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Resource_Category_Tree extends Varien_Data_Tree_Dbp
{
    public const ID_FIELD    = 'id';
    public const PATH_FIELD  = 'path';
    public const ORDER_FIELD = 'order';
    public const LEVEL_FIELD = 'level';

    /**
     * Categories resource collection
     *
     * @var Mage_Catalog_Model_Resource_Category_Collection|null
     */
    protected $_collection;

    /**
     * Id of 'is_active' category attribute
     *
     * @var string|null
     */
    protected $_isActiveAttributeId              = null;

    /**
     * Join URL rewrites data to collection flag
     *
     * @var bool
     */
    protected $_joinUrlRewriteIntoCollection     = false;

    /**
     * Inactive categories ids
     *
     * @var array
     */
    protected $_inactiveCategoryIds              = null;

    /**
     * store id
     *
     * @var int
     */
    protected $_storeId                          = null;

    /**
     * @var array
     */
    protected $_inactiveItems;

    /**
     * Initialize tree
     */
    public function __construct()
    {
        $resource = Mage::getSingleton('core/resource');

        parent::__construct(
            $resource->getConnection('catalog_write'),
            $resource->getTableName('catalog/category'),
            [
                Varien_Data_Tree_Dbp::ID_FIELD       => 'entity_id',
                Varien_Data_Tree_Dbp::PATH_FIELD     => 'path',
                Varien_Data_Tree_Dbp::ORDER_FIELD    => 'position',
                Varien_Data_Tree_Dbp::LEVEL_FIELD    => 'level',
            ],
        );
    }

    /**
     * Set store id
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId($storeId)
    {
        $this->_storeId = $storeId;
        return $this;
    }

    /**
     * Return store id
     *
     * @return int
     */
    public function getStoreId()
    {
        if ($this->_storeId === null) {
            $this->_storeId = Mage::app()->getStore()->getId();
        }
        return $this->_storeId;
    }

    /**
     * @param Mage_Catalog_Model_Resource_Category_Collection $collection
     * @param bool $sorted
     * @param array $exclude
     * @param bool $toLoad
     * @param bool $onlyActive
     * @return $this
     */
    public function addCollectionData(
        $collection = null,
        $sorted = false,
        $exclude = [],
        $toLoad = true,
        $onlyActive = false,
    ) {
        if (is_null($collection)) {
            $collection = $this->getCollection($sorted);
        } else {
            $this->setCollection($collection);
        }

        if (!is_array($exclude)) {
            $exclude = [$exclude];
        }

        $nodeIds = [];
        foreach ($this->getNodes() as $id => $node) {
            if (!in_array($id, $exclude)) {
                $nodeIds[] = $id;
            }
        }
        $collection->addIdFilter($nodeIds);
        if ($onlyActive) {
            $disabledIds = $this->_getDisabledIds($collection);
            if ($disabledIds) {
                $collection->addFieldToFilter('entity_id', ['nin' => $disabledIds]);
            }
            $collection->addAttributeToFilter('is_active', 1);
            $collection->addAttributeToFilter('include_in_menu', 1);
        }

        if ($this->_joinUrlRewriteIntoCollection) {
            $collection->joinUrlRewrite();
            $this->_joinUrlRewriteIntoCollection = false;
        }

        if ($toLoad) {
            $collection->load();

            foreach ($collection as $category) {
                $node = $this->getNodeById($category->getId());
                if ($node) {
                    $node->addData($category->getData());
                }
            }

            foreach ($this->getNodes() as $id => $node) {
                if (!$collection->getItemById($id) && $node->getParent()) {
                    $this->removeNode($node);
                }
            }
        }

        return $this;
    }

    /**
     * Add inactive categories ids
     *
     * @param array $ids
     * @return $this
     */
    public function addInactiveCategoryIds($ids)
    {
        if (!is_array($this->_inactiveCategoryIds)) {
            $this->_initInactiveCategoryIds();
        }
        $this->_inactiveCategoryIds = array_merge($ids, $this->_inactiveCategoryIds);
        return $this;
    }

    /**
     * Retrieve inactive categories ids
     *
     * @return $this
     */
    protected function _initInactiveCategoryIds()
    {
        $this->_inactiveCategoryIds = [];
        Mage::dispatchEvent('catalog_category_tree_init_inactive_category_ids', ['tree' => $this]);
        return $this;
    }

    /**
     * Retrieve inactive categories ids
     *
     * @return array
     */
    public function getInactiveCategoryIds()
    {
        if (!is_array($this->_inactiveCategoryIds)) {
            $this->_initInactiveCategoryIds();
        }

        return $this->_inactiveCategoryIds;
    }

    /**
     * Return disable category ids
     *
     * @param Mage_Catalog_Model_Resource_Category_Collection $collection
     * @return array
     */
    protected function _getDisabledIds($collection)
    {
        $storeId = Mage::app()->getStore()->getId();

        $this->_inactiveItems = $this->getInactiveCategoryIds();

        $this->_inactiveItems = array_merge(
            $this->_getInactiveItemIds($collection, $storeId),
            $this->_inactiveItems,
        );

        $allIds = $collection->getAllIds();
        $disabledIds = [];

        foreach ($allIds as $id) {
            $parents = $this->getNodeById($id)->getPath();
            foreach ($parents as $parent) {
                if (!$this->_getItemIsActive($parent->getId(), $storeId)) {
                    $disabledIds[] = $id;
                    continue;
                }
            }
        }
        return $disabledIds;
    }

    /**
     * Returns attribute id for attribute "is_active"
     *
     * @return string
     */
    protected function _getIsActiveAttributeId()
    {
        $resource = Mage::getSingleton('core/resource');
        if (is_null($this->_isActiveAttributeId)) {
            $bind = [
                'entity_type_code' => Mage_Catalog_Model_Category::ENTITY,
                'attribute_code'   => 'is_active',
            ];
            $select = $this->_conn->select()
                ->from(['a' => $resource->getTableName('eav/attribute')], ['attribute_id'])
                ->join(['t' => $resource->getTableName('eav/entity_type')], 'a.entity_type_id = t.entity_type_id')
                ->where('entity_type_code = :entity_type_code')
                ->where('attribute_code = :attribute_code');

            $this->_isActiveAttributeId = $this->_conn->fetchOne($select, $bind);
        }
        return $this->_isActiveAttributeId;
    }

    /**
     * Retrieve inactive category item ids
     *
     * @param Mage_Catalog_Model_Resource_Category_Collection $collection
     * @param int $storeId
     * @return array
     */
    protected function _getInactiveItemIds($collection, $storeId)
    {
        $filter = $collection->getAllIds();
        $attributeId = $this->_getIsActiveAttributeId();

        $conditionSql = $this->_conn->getCheckSql('c.value_id > 0', 'c.value', 'd.value');
        $table = Mage::getSingleton('core/resource')->getTableName(['catalog/category', 'int']);
        $bind = [
            'attribute_id' => $attributeId,
            'store_id'     => $storeId,
            'zero_store_id' => 0,
            'cond'         => 0,

        ];
        $select = $this->_conn->select()
            ->from(['d' => $table], ['d.entity_id'])
            ->where('d.attribute_id = :attribute_id')
            ->where('d.store_id = :zero_store_id')
            ->where('d.entity_id IN (?)', $filter)
            ->joinLeft(
                ['c' => $table],
                'c.attribute_id = :attribute_id AND c.store_id = :store_id AND c.entity_id = d.entity_id',
                [],
            )
            ->where($conditionSql . ' = :cond');

        return $this->_conn->fetchCol($select, $bind);
    }

    /**
     * Check is category items active
     *
     * @param int $id
     * @return bool
     */
    protected function _getItemIsActive($id)
    {
        if (!in_array($id, $this->_inactiveItems)) {
            return true;
        }
        return false;
    }

    /**
     * Get categories collection
     *
     * @param bool $sorted
     * @return Mage_Catalog_Model_Resource_Category_Collection
     */
    public function getCollection($sorted = false)
    {
        if (is_null($this->_collection)) {
            $this->_collection = $this->_getDefaultCollection($sorted);
        }
        return $this->_collection;
    }

    /**
     * @param Mage_Catalog_Model_Resource_Category_Collection $collection
     * @return $this
     */
    public function setCollection($collection)
    {
        if (!is_null($this->_collection)) {
            destruct($this->_collection);
        }
        $this->_collection = $collection;
        return $this;
    }

    /**
     * @param bool $sorted
     * @return Mage_Catalog_Model_Resource_Category_Collection
     */
    protected function _getDefaultCollection($sorted = false)
    {
        $this->_joinUrlRewriteIntoCollection = true;
        /** @var Mage_Catalog_Model_Resource_Category_Collection $collection */
        $collection = Mage::getModel('catalog/category')->getCollection();

        $attributes = Mage::getConfig()->getNode('frontend/category/collection/attributes');
        if ($attributes) {
            $attributes = $attributes->asArray();
            $attributes = array_keys($attributes);
        }
        $collection->addAttributeToSelect($attributes);

        if ($sorted) {
            if (is_string($sorted)) {
                // $sorted is supposed to be attribute name
                $collection->addAttributeToSort($sorted);
            } else {
                $collection->addAttributeToSort('name');
            }
        }

        return $collection;
    }

    /**
     * Move tree before
     *
     * @param Mage_Catalog_Model_Category $category
     * @param Varien_Data_Tree_Node $newParent
     * @param Varien_Data_Tree_Node $prevNode
     * @return $this
     */
    protected function _beforeMove($category, $newParent, $prevNode)
    {
        Mage::dispatchEvent('catalog_category_tree_move_before', [
            'category'      => $category,
            'prev_parent'   => $prevNode,
            'parent'        => $newParent,
        ]);

        return $this;
    }

    /**
     * Executing parents move method and cleaning cache after it
     *
     * @param Mage_Catalog_Model_Category $category
     * @param Varien_Data_Tree_Node $newParent
     * @param Varien_Data_Tree_Node $prevNode
     */
    #[\Override]
    public function move($category, $newParent, $prevNode = null)
    {
        $this->_beforeMove($category, $newParent, $prevNode);
        Mage::getResourceSingleton('catalog/category')->move($category->getId(), $newParent->getId());
        parent::move($category, $newParent, $prevNode);

        $this->_afterMove($category, $newParent, $prevNode);
    }

    /**
     * Move tree after
     *
     * @param Mage_Catalog_Model_Category $category
     * @param Varien_Data_Tree_Node $newParent
     * @param Varien_Data_Tree_Node $prevNode
     * @return $this
     */
    protected function _afterMove($category, $newParent, $prevNode)
    {
        Mage::app()->cleanCache([Mage_Catalog_Model_Category::CACHE_TAG]);

        Mage::dispatchEvent('catalog_category_tree_move_after', [
            'category'  => $category,
            'prev_node' => $prevNode,
            'parent'    => $newParent,
        ]);

        return $this;
    }

    /**
     * Load category tree including specified categories ids, their parents, children, and siblings.
     *
     * @param array $ids
     * @param bool $addCollectionData
     * @param bool $updateAnchorProductCount
     * @param int|null $recursionLevel Must be a non-negative integer or null
     * @return $this|false
     */
    public function loadByIds($ids, $addCollectionData = true, $updateAnchorProductCount = true, $recursionLevel = null)
    {
        $levelField = $this->_conn->quoteIdentifier('level');
        $pathField  = $this->_conn->quoteIdentifier('path');
        $recursionLevel ??= Mage_Adminhtml_Block_Catalog_Category_Abstract::DEFAULT_RECURSION_LEVEL;

        if (!is_array($ids)) {
            $ids = [$ids];
        }
        foreach ($ids as $key => &$id) {
            $id = (int) $id;
            if ($id <= 0) {
                unset($ids[$key]);
            }
        }

        $where = [];
        if ($recursionLevel !== 0) {
            $where[] = $this->_conn->quoteInto("$levelField <= ?", $recursionLevel + 1);
        }

        // collect paths of specified IDs and build query to collect their parents, children, and siblings
        if (!empty($ids)) {
            $select = $this->_conn->select()
                ->from($this->_table, ['path', 'level'])
                ->where('entity_id IN (?)', $ids);

            foreach ($this->_conn->fetchAll($select) as $item) {
                if (!preg_match("#^[0-9\/]+$#", $item['path'])) {
                    $item['path'] = '';
                }
                $pathIds  = explode('/', $item['path']);
                $level = (int) $item['level'];
                while ($level > $recursionLevel) {
                    $path = implode('/', $pathIds) . '/%';
                    $where[] = $this->_conn->quoteInto("$levelField = ?", $level + 1)
                        . ' AND ' . $this->_conn->quoteInto("$pathField LIKE ?", $path);
                    array_pop($pathIds);
                    $level--;
                }
            }
        }

        // get all required records
        if ($addCollectionData) {
            $select = $this->_createCollectionDataSelect();
        } else {
            $select = clone $this->_select;
            $select->order($this->_orderField . ' ' . Varien_Db_Select::SQL_ASC);
        }
        if (count($where)) {
            $select->where(implode(' OR ', array_unique($where)));
        }

        // get array of records and add them as nodes to the tree
        $arrNodes = $this->_conn->fetchAll($select);
        if (!$arrNodes) {
            return false;
        }
        if ($updateAnchorProductCount) {
            $this->_updateAnchorProductCount($arrNodes);
        }
        $childrenItems = [];
        foreach ($arrNodes as $key => $nodeInfo) {
            $pathToParent = explode('/', $nodeInfo[$this->_pathField]);
            array_pop($pathToParent);
            $pathToParent = implode('/', $pathToParent);
            $childrenItems[$pathToParent][] = $nodeInfo;
        }
        $this->addChildNodes($childrenItems, '', null);
        return $this;
    }

    /**
     * Load array of category parents
     *
     * @param string $path
     * @param bool $addCollectionData
     * @param bool $withRootNode
     * @return array
     */
    public function loadBreadcrumbsArray($path, $addCollectionData = true, $withRootNode = false)
    {
        $pathIds = explode('/', $path ?? '');
        if (!$withRootNode) {
            array_shift($pathIds);
        }
        $result = [];
        if (!empty($pathIds)) {
            if ($addCollectionData) {
                $select = $this->_createCollectionDataSelect(false);
            } else {
                $select = clone $this->_select;
            }
            $select
                ->where('e.entity_id IN(?)', $pathIds)
                ->order($this->_conn->getLengthSql('e.path') . ' ' . Varien_Db_Select::SQL_ASC);
            $result = $this->_conn->fetchAll($select);
            $this->_updateAnchorProductCount($result);
        }
        return $result;
    }

    /**
     * Replace products count with self products count, if category is non-anchor
     *
     * @param array $data
     */
    protected function _updateAnchorProductCount(&$data)
    {
        foreach ($data as $key => $row) {
            if ((int) $row['is_anchor'] === 0) {
                $data[$key]['product_count'] = $row['self_product_count'];
            }
        }
    }

    /**
     * Obtain select for categories with attributes.
     * By default, everything from entity table is selected
     * + name, is_active and is_anchor
     * Also the correct product_count is selected, depending on is the category anchor or not.
     *
     * @param bool $sorted
     * @param array $optionalAttributes
     * @return Zend_Db_Select
     */
    protected function _createCollectionDataSelect($sorted = true, $optionalAttributes = [])
    {
        $select = $this->_getDefaultCollection($sorted ? $this->_orderField : false)
            ->getSelect();
        // add attributes to select
        $attributes = ['name', 'is_active', 'is_anchor'];
        if ($optionalAttributes) {
            $attributes = array_unique(array_merge($attributes, $optionalAttributes));
        }
        foreach ($attributes as $attributeCode) {
            /** @var Mage_Eav_Model_Entity_Attribute $attribute */
            $attribute = Mage::getResourceSingleton('catalog/category')->getAttribute($attributeCode);
            // join non-static attribute table
            if (!$attribute->getBackend()->isStatic()) {
                $tableDefault   = sprintf('d_%s', $attributeCode);
                $tableStore     = sprintf('s_%s', $attributeCode);
                $valueExpr      = $this->_conn
                    ->getCheckSql("{$tableStore}.value_id > 0", "{$tableStore}.value", "{$tableDefault}.value");

                $select
                    ->joinLeft(
                        [$tableDefault => $attribute->getBackend()->getTable()],
                        sprintf(
                            '%1$s.entity_id=e.entity_id AND %1$s.attribute_id=%2$d'
                            . ' AND %1$s.entity_type_id=e.entity_type_id AND %1$s.store_id=%3$d',
                            $tableDefault,
                            $attribute->getId(),
                            Mage_Core_Model_App::ADMIN_STORE_ID,
                        ),
                        [$attributeCode => 'value'],
                    )
                    ->joinLeft(
                        [$tableStore => $attribute->getBackend()->getTable()],
                        sprintf(
                            '%1$s.entity_id=e.entity_id AND %1$s.attribute_id=%2$d'
                            . ' AND %1$s.entity_type_id=e.entity_type_id AND %1$s.store_id=%3$d',
                            $tableStore,
                            $attribute->getId(),
                            $this->getStoreId(),
                        ),
                        [$attributeCode => $valueExpr],
                    );
            }
        }

        // count children products qty plus self products qty
        $categoriesTable         = Mage::getSingleton('core/resource')->getTableName('catalog/category');
        $categoriesProductsTable = Mage::getSingleton('core/resource')->getTableName('catalog/category_product');

        $subConcat = $this->_conn->getConcatSql(['e.path', $this->_conn->quote('/%')]);
        $subSelect = $this->_conn->select()
            ->from(['see' => $categoriesTable], null)
            ->joinLeft(
                ['scp' => $categoriesProductsTable],
                'see.entity_id=scp.category_id',
                ['COUNT(DISTINCT scp.product_id)'],
            )
            ->where('see.entity_id = e.entity_id')
            ->orWhere('see.path LIKE ?', $subConcat);
        $select->columns(['product_count' => $subSelect]);

        $subSelect = $this->_conn->select()
            ->from(['cp' => $categoriesProductsTable], 'COUNT(cp.product_id)')
            ->where('cp.category_id = e.entity_id');

        $select->columns(['self_product_count' => $subSelect]);

        return $select;
    }

    /**
     * Get real existing category ids by specified ids
     *
     * @param array $ids
     * @return array
     */
    public function getExistingCategoryIdsBySpecifiedIds($ids)
    {
        if (empty($ids)) {
            return [];
        }
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        $select = $this->_conn->select()
            ->from($this->_table, ['entity_id'])
            ->where('entity_id IN (?)', $ids);
        return $this->_conn->fetchCol($select);
    }
}
