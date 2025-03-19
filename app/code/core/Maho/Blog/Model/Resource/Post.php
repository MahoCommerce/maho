<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Model_Resource_Post extends Mage_Eav_Model_Entity_Abstract
{
    protected string $_storeTable;

    public function __construct()
    {
        $this->setType('blog_post');
        $this->_storeTable = $this->getTable('blog/post_store');

        $resource = Mage::getSingleton('core/resource');
        $this->setConnection(
            $resource->getConnection('sales_read'),
            $resource->getConnection('sales_write'),
        );
    }

    #[\Override]
    protected function _getDefaultAttributes()
    {
        return [
            'entity_type_id',
            'attribute_set_id',
            'increment_id',
            'created_at',
            'updated_at',
            'is_active',
            'url_key',
        ];
    }

    #[\Override]
    protected function _afterSave(Maho_Blog_Model_Post $object)
    {
        if ($object->hasStores()) {
            $this->_saveStoreRelations($object);
        }

        return parent::_afterSave($object);
    }

    protected function _saveStoreRelations(Maho_Blog_Model_Post $post): void
    {
        $oldStores = $this->lookupStoreIds($post->getId());
        $newStores = (array) $post->getStores();

        $table = $this->_storeTable;
        $adapter = $this->_getWriteAdapter();

        // Delete removed stores
        $delete = array_diff($oldStores, $newStores);
        if (!empty($delete)) {
            $adapter->delete($table, [
                'post_id = ?' => (int) $post->getId(),
                'store_id IN (?)' => $delete,
            ]);
        }

        // Insert new stores
        $insert = array_diff($newStores, $oldStores);
        if (!empty($insert)) {
            $data = [];
            foreach ($insert as $storeId) {
                $data[] = [
                    'post_id' => (int) $post->getId(),
                    'store_id' => (int) $storeId,
                ];
            }
            $adapter->insertMultiple($table, $data);
        }
    }

    public function lookupStoreIds(int $postId): array
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->_storeTable, 'store_id')
            ->where('post_id = ?', (int) $postId);

        return $adapter->fetchCol($select);
    }

    #[\Override]
    protected function _afterLoad(Varien_Object $object): self
    {
        if ($object->getId()) {
            $stores = $this->lookupStoreIds($object->getId());
            $object->setData('stores', $stores);
        }

        return parent::_afterLoad($object);
    }

    public function getPostIdByUrlKey(string $urlKey, int $storeId): ?int
    {
        $urlKey = substr($urlKey, strlen('blog/'));
        $urlKey = substr($urlKey, 0, -strlen('.html'));

        $stores = [Mage_Core_Model_App::ADMIN_STORE_ID, $storeId];
        $select = $this->getLoadByUrkKeySelect($urlKey, $stores, true);
        $select->reset(Zend_Db_Select::COLUMNS)
            ->columns('bp.entity_id')
            ->order('bps.store_id DESC')
            ->limit(1);

        $result = $this->_getReadAdapter()->fetchOne($select);
        return $result ? (int) $result : null;
    }

    protected function getLoadByUrkKeySelect(string $urlKey, array $store, ?bool $isActive = null): Varien_Db_Select
    {
        $select = $this->_getReadAdapter()->select()
            ->from(['bp' => $this->getEntityTable()])
            ->join(
                ['bps' => $this->getTable('blog/post_store')],
                'bp.entity_id = bps.post_id',
                [],
            )
            ->where('bp.url_key = ?', $urlKey)
            ->where('bps.store_id IN (?)', $store);

        if (!is_null($isActive)) {
            $select->where('bp.is_active = ?', (int) $isActive);
        }

        return $select;
    }

    #[\Override]
    protected function _beforeSave(Varien_Object $object): self
    {
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
            $urlkey = Mage::getModel('catalog/product_url')->formatUrlKey($object->getData('title'), $locale);
            $object->setData('url_key', $urlkey);
        }

        return parent::_beforeSave($object);
    }
}
