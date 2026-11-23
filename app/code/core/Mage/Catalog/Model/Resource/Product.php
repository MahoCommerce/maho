<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Resource_Product extends Mage_Catalog_Model_Resource_Abstract
{
    /**
     * Product to website linkage table
     *
     * @var string
     */
    protected $_productWebsiteTable;

    /**
     * Product to category linkage table
     *
     * @var string
     */
    protected $_productCategoryTable;

    /**
     * Initialize resource
     */
    public function __construct()
    {
        parent::__construct();
        $this->setType(Mage_Catalog_Model_Product::ENTITY)
             ->setConnection('catalog_read', 'catalog_write');
        $this->_productWebsiteTable  = $this->getTable('catalog/product_website');
        $this->_productCategoryTable = $this->getTable('catalog/category_product');
    }

    /**
     * Default product attributes
     *
     * @return array
     */
    #[\Override]
    protected function _getDefaultAttributes()
    {
        return ['entity_id', 'entity_type_id', 'attribute_set_id', 'type_id', 'created_at', 'updated_at'];
    }

    /**
     * Return the backend model of the media_gallery attribute
     *
     * @throws \UnexpectedValueException when the attribute does not exist or its backend does not extend Mage_Catalog_Model_Product_Attribute_Backend_Media
     */
    public function getMediaGalleryBackend(): Mage_Catalog_Model_Product_Attribute_Backend_Media
    {
        $attribute = $this->getAttribute('media_gallery');
        $backend = $attribute ? $attribute->getBackend() : null;
        if (!$backend instanceof Mage_Catalog_Model_Product_Attribute_Backend_Media) {
            throw new \UnexpectedValueException('The backend model of the media_gallery attribute must extend Mage_Catalog_Model_Product_Attribute_Backend_Media.');
        }
        return $backend;
    }

    /**
     * Retrieve product website identifiers
     *
     * @param Mage_Catalog_Model_Product|int $product
     * @return array
     */
    public function getWebsiteIds($product)
    {
        $adapter = $this->_getReadAdapter();

        if ($product instanceof Mage_Catalog_Model_Product) {
            $productId = $product->getId();
        } else {
            $productId = $product;
        }

        $select = $adapter->select()
            ->from($this->_productWebsiteTable, 'website_id')
            ->where('product_id = ?', (int) $productId);

        return $adapter->fetchCol($select);
    }

    /**
     * Retrieve product website identifiers by product identifiers
     *
     * @param array $productIds
     * @return  array
     */
    public function getWebsiteIdsByProductIds($productIds)
    {
        $select = $this->_getWriteAdapter()->select()
            ->from($this->_productWebsiteTable, ['product_id', 'website_id'])
            ->where('product_id IN (?)', $productIds);
        $productsWebsites = [];
        foreach ($this->_getWriteAdapter()->fetchAll($select) as $productInfo) {
            $productId = $productInfo['product_id'];
            $productsWebsites[$productId] ??= [];
            $productsWebsites[$productId][] = $productInfo['website_id'];
        }

        return $productsWebsites;
    }

    /**
     * Return the IDs of $productIds that are in none of the websites $websiteIds.
     *
     * @param array<int|string> $productIds
     * @param array<int|string> $websiteIds
     * @return list<int>
     */
    public function getIdsNotInWebsites(array $productIds, array $websiteIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $websiteIds = array_map(intval(...), $websiteIds);
        $productWebsites = $this->getWebsiteIdsByProductIds($productIds);
        $outside = [];
        foreach ($productIds as $productId) {
            if (array_intersect(array_map(intval(...), $productWebsites[$productId] ?? []), $websiteIds) === []) {
                $outside[] = (int) $productId;
            }
        }
        return $outside;
    }

    /**
     * Retrieve product category identifiers
     *
     * @param Mage_Catalog_Model_Product|\Maho\DataObject $product
     * @return array
     */
    public function getCategoryIds($product)
    {
        $adapter = $this->_getReadAdapter();

        $select = $adapter->select()
            ->from($this->_productCategoryTable, 'category_id')
            ->where('product_id = ?', (int) $product->getId());

        return $adapter->fetchCol($select);
    }

    /**
     * Get product identifier by sku
     *
     * @param string $sku
     * @return string
     */
    public function getIdBySku($sku)
    {
        $adapter = $this->_getReadAdapter();

        $select = $adapter->select()
            ->from($this->getEntityTable(), 'entity_id')
            ->where('sku = :sku');

        $bind = [':sku' => (string) $sku];

        return $adapter->fetchOne($select, $bind);
    }

    /**
     * @param Mage_Catalog_Model_Product $object
     */
    #[\Override]
    protected function _beforeSave(\Maho\DataObject $object)
    {
        /**
         * Try to detect product id by sku if id is not declared
         */
        if (!$object->getId() && $object->getSku()) {
            $object->setId($this->getIdBySku($object->getSku()));
        }

        /**
         * Check if declared category ids in object data.
         */
        if ($object->hasCategoryIds()) {
            $categoryIds = Mage::getResourceSingleton('catalog/category')->verifyIds(
                $object->getCategoryIds(),
            );
            $object->setCategoryIds($categoryIds);
        }

        return parent::_beforeSave($object);
    }

    /**
     * Save data related with product
     *
     * @param Mage_Catalog_Model_Product $product
     */
    #[\Override]
    protected function _afterSave(\Maho\DataObject $product)
    {
        $this->_saveWebsiteIds($product)
            ->_saveCategories($product);

        return parent::_afterSave($product);
    }

    /**
     * Save product website relations
     *
     * @param Mage_Catalog_Model_Product $product
     * @return $this
     */
    protected function _saveWebsiteIds($product)
    {
        $websiteIds = $product->getWebsiteIds();

        $product->setIsChangedWebsites(false);

        $adapter = $this->_getWriteAdapter();

        $oldWebsiteIds = $this->getWebsiteIds($product);

        $insert = array_diff($websiteIds, $oldWebsiteIds);
        $delete = array_diff($oldWebsiteIds, $websiteIds);

        if (!empty($insert)) {
            $data = [];
            foreach ($insert as $websiteId) {
                $data[] = [
                    'product_id' => (int) $product->getId(),
                    'website_id' => (int) $websiteId,
                ];
            }
            $adapter->insertMultiple($this->_productWebsiteTable, $data);
        }

        if (!empty($delete)) {
            foreach ($delete as $websiteId) {
                $condition = [
                    'product_id = ?' => (int) $product->getId(),
                    'website_id = ?' => (int) $websiteId,
                ];

                $adapter->delete($this->_productWebsiteTable, $condition);
            }
        }

        if (!empty($insert) || !empty($delete)) {
            $product->setIsChangedWebsites();
        }

        return $this;
    }

    /**
     * Save product category relations
     *
     * @return $this
     */
    protected function _saveCategories(\Maho\DataObject $object)
    {
        /**
         * If category ids data is not declared we haven't do manipulations
         */
        if (!$object->hasCategoryIds()) {
            return $this;
        }
        $categoryIds = $object->getCategoryIds();
        $oldCategoryIds = $this->getCategoryIds($object);

        $object->setIsChangedCategories(false);

        $insert = array_diff($categoryIds, $oldCategoryIds);
        $delete = array_diff($oldCategoryIds, $categoryIds);

        $write = $this->_getWriteAdapter();
        if (!empty($insert)) {
            $data = [];
            foreach ($insert as $categoryId) {
                if (empty($categoryId)) {
                    continue;
                }
                $data[] = [
                    'category_id' => (int) $categoryId,
                    'product_id'  => (int) $object->getId(),
                    'position'    => 1,
                ];
            }
            if ($data) {
                $write->insertMultiple($this->_productCategoryTable, $data);
            }
        }

        if (!empty($delete)) {
            foreach ($delete as $categoryId) {
                $where = [
                    'product_id = ?'  => (int) $object->getId(),
                    'category_id = ?' => (int) $categoryId,
                ];

                $write->delete($this->_productCategoryTable, $where);
            }
        }

        if (!empty($insert) || !empty($delete)) {
            $object->setAffectedCategoryIds(array_merge($insert, $delete));
            $object->setIsChangedCategories(true);
        }

        return $this;
    }

    /**
     * Refresh the enabled index of a store, for the given products or for all products of the store
     *
     * @param array<int|string>|null $productIds
     */
    public function refreshEnabledIndex(Mage_Core_Model_Store $store, ?array $productIds = null): static
    {
        $this->_refreshEnabledIndex($store, $productIds ?? []);
        return $this;
    }

    /**
     * Refresh the enabled index of one product in a store
     */
    public function refreshProductEnabledIndex(Mage_Core_Model_Store $store, int $productId): static
    {
        $this->_refreshEnabledIndex($store, [$productId]);
        return $this;
    }

    /**
     * @param array<int|string> $productIds An empty list refreshes all products of the store
     */
    protected function _refreshEnabledIndex(Mage_Core_Model_Store $store, array $productIds): void
    {
        $statusAttribute        = $this->getAttribute('status');
        $visibilityAttribute    = $this->getAttribute('visibility');
        $statusAttributeId      = $statusAttribute->getId();
        $visibilityAttributeId  = $visibilityAttribute->getId();
        $statusTable            = $statusAttribute->getBackend()->getTable();
        $visibilityTable        = $visibilityAttribute->getBackend()->getTable();

        $adapter = $this->_getWriteAdapter();

        $select = $adapter->select();
        $condition = [];

        $indexTable = $this->getTable('catalog/product_enabled_index');
        $storeId    = $store->getId();
        $websiteId  = $store->getWebsiteId();

        if ($productIds) {
            $condition[] = $adapter->quoteInto('product_id IN (?)', $productIds);
        }

        $condition[] = $adapter->quoteInto('store_id = ?', $storeId);

        $selectFields = [
            't_v_default.entity_id',
            new Maho\Db\Expr((string) $storeId),
            $adapter->getCheckSql('t_v.value_id > 0', 't_v.value', 't_v_default.value'),
        ];

        $adapter->delete($indexTable, $condition);

        $select->from(['t_v_default' => $visibilityTable], $selectFields);

        // A join that comes before from() loses its condition, so the website join comes here
        $select->joinInner(
            ['w' => $this->getTable('catalog/product_website')],
            $adapter->quoteInto('w.product_id = t_v_default.entity_id AND w.website_id = ?', $websiteId),
            [],
        );

        $visibilityTableJoinCond = [
            't_v.entity_id = t_v_default.entity_id',
            $adapter->quoteInto('t_v.attribute_id = ?', $visibilityAttributeId),
            $adapter->quoteInto('t_v.store_id     = ?', $storeId),
        ];

        $select->joinLeft(
            ['t_v' => $visibilityTable],
            implode(' AND ', $visibilityTableJoinCond),
            [],
        );

        $defaultStatusJoinCond = [
            't_s_default.entity_id = t_v_default.entity_id',
            't_s_default.store_id = 0',
            $adapter->quoteInto('t_s_default.attribute_id = ?', $statusAttributeId),
        ];

        $select->joinInner(
            ['t_s_default' => $statusTable],
            implode(' AND ', $defaultStatusJoinCond),
            [],
        );

        $statusJoinCond = [
            't_s.entity_id = t_v_default.entity_id',
            $adapter->quoteInto('t_s.store_id     = ?', $storeId),
            $adapter->quoteInto('t_s.attribute_id = ?', $statusAttributeId),
        ];

        $select->joinLeft(
            ['t_s' => $statusTable],
            implode(' AND ', $statusJoinCond),
            [],
        );

        $valueCondition = $adapter->getCheckSql('t_s.value_id > 0', 't_s.value', 't_s_default.value');

        $select->where('t_v_default.attribute_id = ?', $visibilityAttributeId)
            ->where('t_v_default.store_id = ?', 0)
            ->where(sprintf('%s = ?', $valueCondition), Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

        if ($productIds) {
            $select->where('t_v_default.entity_id IN (?)', $productIds);
        }

        $adapter->query($adapter->insertFromSelect($select, $indexTable, [], \Maho\Db\Adapter\AdapterInterface::INSERT_ON_DUPLICATE));
    }

    /**
     * Get collection of product categories
     *
     * @param Mage_Catalog_Model_Product $product
     * @return Mage_Catalog_Model_Resource_Category_Collection
     */
    public function getCategoryCollection($product)
    {
        return Mage::getResourceModel('catalog/category_collection')
            ->joinField(
                'product_id',
                'catalog/category_product',
                'product_id',
                'category_id = entity_id',
                null,
            )
            ->addFieldToFilter('product_id', (int) $product->getId());
    }

    /**
     * Retrieve category ids where product is available
     *
     * @param Mage_Catalog_Model_Product $object
     * @return array
     */
    public function getAvailableInCategories($object)
    {
        // is_parent=1 ensures that we'll get only category IDs those are direct parents of the product, instead of
        // fetching all parent IDs, including those are higher on the tree
        $select = $this->_getReadAdapter()->select()->distinct()
            ->from($this->getTable('catalog/category_product_index'), ['category_id'])
            ->where('product_id = ? AND is_parent = 1', (int) $object->getEntityId());

        return $this->_getReadAdapter()->fetchCol($select);
    }

    /**
     * Get default attribute source model
     *
     * @return string
     */
    #[\Override]
    public function getDefaultAttributeSourceModel()
    {
        return 'eav/entity_attribute_source_table';
    }

    /**
     * Check availability display product in category
     *
     * @param Mage_Catalog_Model_Product $product
     * @param int $categoryId
     * @return string
     */
    public function canBeShowInCategory($product, $categoryId)
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getTable('catalog/category_product_index'), 'product_id')
            ->where('product_id = ?', (int) $product->getId())
            ->where('category_id = ?', (int) $categoryId);

        return $this->_getReadAdapter()->fetchOne($select);
    }

    /**
     * Get the category of $product that the store shows when the request names no category.
     *
     * The candidates are the categories of the product below the root category of the store. A candidate
     * with an inactive category in its path is skipped. A candidate whose whole path is in the navigation
     * menu wins over the others. Then the deepest candidate wins, then the lowest ID.
     */
    public function getPrimaryCategoryId(Mage_Catalog_Model_Product $product, int $storeId): ?int
    {
        $adapter = $this->_getReadAdapter();
        $rootPath = Mage_Catalog_Model_Category::TREE_ROOT_ID . '/' . (int) Mage::app()->getStore($storeId)->getRootCategoryId();
        $paths = $adapter->fetchPairs(
            $adapter->select()
                ->from(['cp' => $this->_productCategoryTable], ['category_id'])
                ->join(['ce' => $this->getTable('catalog/category')], 'ce.entity_id = cp.category_id', ['path'])
                ->where('cp.product_id = ?', (int) $product->getId())
                ->where('ce.path LIKE ?', $rootPath . '/%'),
        );
        if ($paths === []) {
            return null;
        }

        $pathIds = [];
        foreach ($paths as $categoryId => $path) {
            $pathIds[(int) $categoryId] = array_map(intval(...), array_slice(explode('/', (string) $path), 2));
        }
        $flags = $this->_getCategoryMenuFlags(array_values(array_unique(array_merge(...array_values($pathIds)))), $storeId);

        $candidates = [];
        foreach ($pathIds as $categoryId => $ids) {
            $isActive = true;
            $inMenu = true;
            foreach ($ids as $id) {
                $isActive = $isActive && ($flags[$id]['is_active'] ?? false);
                $inMenu = $inMenu && ($flags[$id]['include_in_menu'] ?? false);
            }
            if ($isActive) {
                $candidates[] = [$inMenu ? 0 : 1, -count($ids), $categoryId];
            }
        }
        if ($candidates === []) {
            return null;
        }
        sort($candidates);

        return $candidates[0][2];
    }

    /**
     * Get the is_active and include_in_menu values of each category in $categoryIds for $storeId
     *
     * A store value replaces the default value. A missing value is false, as in the navigation menu.
     *
     * @param list<int> $categoryIds
     * @return array<int, array{is_active?: bool, include_in_menu?: bool}>
     */
    protected function _getCategoryMenuFlags(array $categoryIds, int $storeId): array
    {
        $eavConfig = Mage::getSingleton('eav/config');
        $codes = [];
        $table = '';
        foreach (['is_active', 'include_in_menu'] as $code) {
            $attribute = $eavConfig->getAttribute(Mage_Catalog_Model_Category::ENTITY, $code);
            $codes[(int) $attribute->getId()] = $code;
            $table = $attribute->getBackendTable();
        }

        $adapter = $this->_getReadAdapter();
        $rows = $adapter->fetchAll(
            $adapter->select()
                ->from($table, ['entity_id', 'attribute_id', 'value'])
                ->where('entity_id IN (?)', $categoryIds)
                ->where('attribute_id IN (?)', array_keys($codes))
                ->where('store_id IN (?)', array_unique([Mage_Core_Model_App::ADMIN_STORE_ID, $storeId]))
                ->order('store_id ASC'),
        );

        $flags = [];
        foreach ($rows as $row) {
            $flags[(int) $row['entity_id']][$codes[(int) $row['attribute_id']]] = (int) $row['value'] === 1;
        }
        return $flags;
    }

    /**
     * Duplicate product store values
     *
     * @param int $oldId
     * @param int $newId
     * @return $this
     */
    public function duplicate($oldId, $newId)
    {
        $eavTables = ['datetime', 'decimal', 'int', 'text', 'varchar'];

        $adapter = $this->_getWriteAdapter();

        // duplicate EAV store values
        foreach ($eavTables as $suffix) {
            $tableName = $this->getTable(['catalog/product', $suffix]);

            $select = $adapter->select()
                ->from($tableName, [
                    'entity_type_id',
                    'attribute_id',
                    'store_id',
                    'entity_id' => new Maho\Db\Expr($adapter->quote($newId)),
                    'value',
                ])
                ->where('entity_id = ?', $oldId)
                ->where('store_id > ?', 0);

            $adapter->query($adapter->insertFromSelect(
                $select,
                $tableName,
                [
                    'entity_type_id',
                    'attribute_id',
                    'store_id',
                    'entity_id',
                    'value',
                ],
                Maho\Db\Adapter\AdapterInterface::INSERT_ON_DUPLICATE,
            ));
        }

        // set status as disabled
        $statusAttribute      = $this->getAttribute('status');
        $statusAttributeId    = $statusAttribute->getAttributeId();
        $statusAttributeTable = $statusAttribute->getBackend()->getTable();
        $updateCond[]         = 'store_id > 0';
        $updateCond[]         = $adapter->quoteInto('entity_id = ?', $newId);
        $updateCond[]         = $adapter->quoteInto('attribute_id = ?', $statusAttributeId);
        $adapter->update(
            $statusAttributeTable,
            ['value' => Mage_Catalog_Model_Product_Status::STATUS_DISABLED],
            $updateCond,
        );

        return $this;
    }

    /**
     * Get SKU through product identifiers
     *
     * @return array
     */
    public function getProductsSku(array $productIds)
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getTable('catalog/product'), ['entity_id', 'sku'])
            ->where('entity_id IN (?)', $productIds);
        return $this->_getReadAdapter()->fetchAll($select);
    }

    /**
     * Retrieve product entities info
     *
     * @param  array|string|null $columns
     * @return array
     */
    public function getProductEntitiesInfo($columns = null)
    {
        if (!empty($columns) && is_string($columns)) {
            $columns = [$columns];
        }
        if (empty($columns)) {
            $columns = $this->_getDefaultAttributes();
        }

        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getTable('catalog/product'), $columns);

        return $adapter->fetchAll($select);
    }

    /**
     * Return assigned images for specific stores
     *
     * @param Mage_Catalog_Model_Product $product
     * @param int|array $storeIds
     * @return array
     */
    public function getAssignedImages($product, $storeIds)
    {
        if (!is_array($storeIds)) {
            $storeIds = [$storeIds];
        }

        $mainTable = $product->getResource()->getAttribute('image')
            ->getBackend()
            ->getTable();
        $read      = $this->_getReadAdapter();
        $select    = $read->select()
            ->from(
                ['images' => $mainTable],
                ['value as filepath', 'store_id'],
            )
            ->joinLeft(
                ['attr' => $this->getTable('eav/attribute')],
                'images.attribute_id = attr.attribute_id',
                ['attribute_code'],
            )
            ->where('entity_id = ?', $product->getId())
            ->where('store_id IN (?)', $storeIds)
            ->where('attribute_code = ?', 'image');

        return $read->fetchAll($select);
    }

    /**
     * Retrieve product categories
     *
     * @param Mage_Catalog_Model_Product $object
     * @return array
     */
    public function getCategoryIdsWithAnchors($object)
    {
        $selectRootCategories = $this->_getReadAdapter()->select()
            ->from(
                [$this->getTable('catalog/category')],
                ['entity_id'],
            )
            ->where('level <= 1');
        $rootIds = $this->_getReadAdapter()->fetchCol($selectRootCategories);
        $select = $this->_getReadAdapter()->select()
            ->from(
                [$this->getTable('catalog/category_product_index')],
                ['category_id'],
            )
            ->where('product_id = ?', (int) $object->getEntityId())
            ->where('category_id NOT IN(?)', $rootIds);

        return $this->_getReadAdapter()->fetchCol($select);
    }
}
