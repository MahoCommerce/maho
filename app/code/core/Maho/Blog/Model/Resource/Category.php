<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Model_Resource_Category extends Mage_Eav_Model_Entity_Abstract
{
    protected string $_storeTable;
    protected string $_postCategoryTable;

    public function __construct()
    {
        $this->setType('blog_category');
        $this->_storeTable = $this->getTable('blog/category_store');
        $this->_postCategoryTable = $this->getTable('blog/post_category');

        $resource = Mage::getSingleton('core/resource');
        $this->setConnection(
            $resource->getConnection('core_read'),
            $resource->getConnection('core_write'),
        );
    }

    #[\Override]
    protected function _getDefaultAttributes()
    {
        return [
            'entity_type_id',
            'attribute_set_id',
            'parent_id',
            'path',
            'level',
            'position',
            'name',
            'url_key',
            'is_active',
            'meta_title',
            'meta_keywords',
            'meta_description',
            'meta_robots',
            'created_at',
            'updated_at',
        ];
    }

    public function getStaticAttributeCodes(): array
    {
        return [
            'parent_id',
            'path',
            'level',
            'position',
            'name',
            'url_key',
            'is_active',
            'meta_title',
            'meta_keywords',
            'meta_description',
            'meta_robots',
        ];
    }

    #[\Override]
    protected function _beforeSave(\Maho\DataObject $object): self
    {
        $coreHelper = Mage::helper('core');

        // Strip HTML tags from string fields
        $stringFields = ['name', 'url_key', 'meta_title', 'meta_keywords', 'meta_description'];
        foreach ($stringFields as $field) {
            if ($object->hasData($field)) {
                $value = $coreHelper->filterStripTags($object->getData($field));
                $object->setData($field, trim($value));
            }
        }

        // Ensure is_active is a valid boolean
        $isActive = $coreHelper->filterInt($object->getData('is_active') ?? 0) ? 1 : 0;
        $object->setData('is_active', $isActive);

        // Auto-generate URL key from name if empty
        if (empty($object->getData('url_key'))) {
            $storeId = null;
            if (is_array($object->getData('stores'))) {
                foreach ($object->getData('stores') as $store) {
                    if (!empty($store)) {
                        $storeId = $store;
                        break;
                    }
                }
            }
            $locale = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $storeId);
            $urlKey = Mage::getModel('catalog/product_url')->formatUrlKey($object->getData('name'), $locale);
            $object->setData('url_key', $urlKey);
        }

        // Calculate level and path from parent
        $parentId = (int) $object->getData('parent_id');
        if (!$parentId) {
            $parentId = Maho_Blog_Model_Category::ROOT_PARENT_ID;
            $object->setData('parent_id', $parentId);
        }

        if ($parentId === Maho_Blog_Model_Category::ROOT_PARENT_ID) {
            // Top-level category
            $object->setData('level', 1);
            if ($object->getId()) {
                $object->setData('path', (string) $object->getId());
            }
        } else {
            $adapter = $this->_getReadAdapter();
            $select = $adapter->select()
                ->from($this->getEntityTable(), ['path', 'level'])
                ->where('entity_id = ?', $parentId);
            $parent = $adapter->fetchRow($select);

            if ($parent) {
                $object->setData('level', (int) $parent['level'] + 1);
                if ($object->getId()) {
                    $object->setData('path', $parent['path'] . '/' . (int) $object->getId());
                }
            }
        }

        return parent::_beforeSave($object);
    }

    #[\Override]
    protected function _afterSave(\Maho\DataObject $object)
    {
        // Finalize path for new categories (entity_id not known during _beforeSave)
        $currentPath = $object->getData('path');
        if (!$currentPath || !str_contains($currentPath, (string) $object->getId())) {
            $parentId = (int) $object->getData('parent_id');

            if ($parentId === Maho_Blog_Model_Category::ROOT_PARENT_ID) {
                $path = (string) $object->getId();
            } else {
                $adapter = $this->_getReadAdapter();
                $select = $adapter->select()
                    ->from($this->getEntityTable(), ['path'])
                    ->where('entity_id = ?', $parentId);
                $parentPath = $adapter->fetchOne($select);
                $path = $parentPath ? $parentPath . '/' . $object->getId() : (string) $object->getId();
            }

            $this->_getWriteAdapter()->update(
                $this->getEntityTable(),
                ['path' => $path],
                ['entity_id = ?' => $object->getId()],
            );
            $object->setData('path', $path);
        }

        if ($object->hasStores()) {
            $this->_saveStoreRelations($object);
        }

        if ($object->hasData('post_ids')) {
            $this->_savePostRelations($object);
        }

        return parent::_afterSave($object);
    }

    #[\Override]
    public function save(\Maho\DataObject $object)
    {
        $locale = Mage::app()->getLocale();

        if (!$object->getId()) {
            $object->setCreatedAt($locale->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT));
        }
        $object->setUpdatedAt($locale->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT));

        parent::save($object);

        $this->_saveStaticAttributes($object);

        return $this;
    }

    protected function _saveStaticAttributes(\Maho\DataObject $object): self
    {
        $adapter = $this->_getWriteAdapter();
        $table = $this->getEntityTable();
        $staticAttributes = $this->getStaticAttributeCodes();

        $data = [];
        foreach ($staticAttributes as $attributeCode) {
            if ($object->hasData($attributeCode)) {
                $data[$attributeCode] = $object->getData($attributeCode);
            }
        }

        if (!empty($data) && $object->getId()) {
            $adapter->update($table, $data, ['entity_id = ?' => $object->getId()]);
        }

        return $this;
    }

    #[\Override]
    protected function _afterLoad(\Maho\DataObject $object): self
    {
        if ($object->getId()) {
            $stores = $this->lookupStoreIds($object->getId());
            $object->setData('stores', $stores);
        }

        return parent::_afterLoad($object);
    }

    protected function _saveStoreRelations(\Maho\DataObject $category): void
    {
        $oldStores = $this->lookupStoreIds($category->getId());
        $newStores = (array) $category->getStores();

        $table = $this->_storeTable;
        $adapter = $this->_getWriteAdapter();

        $delete = array_diff($oldStores, $newStores);
        if (!empty($delete)) {
            $adapter->delete($table, [
                'category_id = ?' => (int) $category->getId(),
                'store_id IN (?)' => $delete,
            ]);
        }

        $insert = array_diff($newStores, $oldStores);
        if (!empty($insert)) {
            $data = [];
            foreach ($insert as $storeId) {
                $data[] = [
                    'category_id' => (int) $category->getId(),
                    'store_id' => (int) $storeId,
                ];
            }
            $adapter->insertMultiple($table, $data);
        }
    }

    protected function _savePostRelations(\Maho\DataObject $category): void
    {
        $oldPostIds = $this->lookupPostIds($category->getId());
        $newPostIds = (array) $category->getData('post_ids');

        $table = $this->_postCategoryTable;
        $adapter = $this->_getWriteAdapter();

        $delete = array_diff($oldPostIds, $newPostIds);
        if (!empty($delete)) {
            $adapter->delete($table, [
                'category_id = ?' => (int) $category->getId(),
                'post_id IN (?)' => $delete,
            ]);
        }

        $insert = array_diff($newPostIds, $oldPostIds);
        if (!empty($insert)) {
            $data = [];
            foreach ($insert as $postId) {
                $data[] = [
                    'post_id' => (int) $postId,
                    'category_id' => (int) $category->getId(),
                    'position' => 0,
                ];
            }
            $adapter->insertMultiple($table, $data);
        }
    }

    public function lookupStoreIds(int $categoryId): array
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->_storeTable, 'store_id')
            ->where('category_id = ?', (int) $categoryId);

        return $adapter->fetchCol($select);
    }

    public function lookupPostIds(int $categoryId): array
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->_postCategoryTable, 'post_id')
            ->where('category_id = ?', (int) $categoryId);

        return $adapter->fetchCol($select);
    }

    public function getCategoryIdByUrlKey(string $urlKey, int $storeId): ?int
    {
        $stores = [Mage_Core_Model_App::ADMIN_STORE_ID, $storeId];
        $adapter = $this->_getReadAdapter();

        $select = $adapter->select()
            ->from(['bc' => $this->getEntityTable()], ['entity_id'])
            ->join(
                ['bcs' => $this->_storeTable],
                'bc.entity_id = bcs.category_id',
                [],
            )
            ->where('bc.url_key = ?', $urlKey)
            ->where('bcs.store_id IN (?)', $stores)
            ->where('bc.is_active = ?', 1)
            ->order('bcs.store_id DESC')
            ->limit(1);

        $result = $adapter->fetchOne($select);
        return $result ? (int) $result : null;
    }
}
