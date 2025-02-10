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

    protected function _getDefaultAttributes()
    {
        return [
            'entity_type_id',
            'attribute_set_id',
            'increment_id',
            'created_at',
            'updated_at',
            'is_active',
        ];
    }

    protected function _afterSave(Varien_Object $object)
    {
        if ($object->hasStores()) {
            $this->_saveStoreRelations($object);
        }

        return parent::_afterSave($object);
    }

    protected function _saveStoreRelations($post)
    {
        $oldStores = $this->lookupStoreIds($post->getId());
        $newStores = (array)$post->getStores();

        $table = $this->_storeTable;
        $adapter = $this->_getWriteAdapter();

        // Delete removed stores
        $delete = array_diff($oldStores, $newStores);
        if (!empty($delete)) {
            $adapter->delete($table, [
                'post_id = ?' => (int)$post->getId(),
                'store_id IN (?)' => $delete
            ]);
        }

        // Insert new stores
        $insert = array_diff($newStores, $oldStores);
        if (!empty($insert)) {
            $data = [];
            foreach ($insert as $storeId) {
                $data[] = [
                    'post_id' => (int)$post->getId(),
                    'store_id' => (int)$storeId
                ];
            }
            $adapter->insertMultiple($table, $data);
        }
    }

    public function lookupStoreIds($postId)
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->_storeTable, 'store_id')
            ->where('post_id = ?', (int)$postId);

        return $adapter->fetchCol($select);
    }

    protected function _afterLoad(Varien_Object $object)
    {
        if ($object->getId()) {
            $stores = $this->lookupStoreIds($object->getId());
            $object->setData('stores', $stores);
        }

        return parent::_afterLoad($object);
    }
}
