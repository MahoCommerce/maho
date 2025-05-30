<?php

/**
 * Maho
 *
 * @package    Mage_Bundle
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Bundle_Model_Resource_Option extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('bundle/option', 'option_id');
    }

    /**
     * @return $this
     */
    #[\Override]
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        parent::_afterSave($object);

        $condition = [
            'option_id = ?' => $object->getId(),
            'store_id = ? OR store_id = 0' => $object->getStoreId(),
        ];

        $write = $this->_getWriteAdapter();
        $write->delete($this->getTable('bundle/option_value'), $condition);

        $data = new Varien_Object();
        $data->setOptionId($object->getId())
            ->setStoreId($object->getStoreId())
            ->setTitle($object->getTitle());

        $write->insert($this->getTable('bundle/option_value'), $data->getData());

        /**
         * also saving default value if this store view scope
         */

        if ($object->getStoreId()) {
            $data->setStoreId(0);
            $data->setTitle($object->getDefaultTitle());
            $write->insert($this->getTable('bundle/option_value'), $data->getData());
        }

        return $this;
    }

    /**
     * After delete process
     *
     * @return $this
     */
    #[\Override]
    protected function _afterDelete(Mage_Core_Model_Abstract $object)
    {
        parent::_afterDelete($object);

        $this->_getWriteAdapter()->delete(
            $this->getTable('bundle/option_value'),
            ['option_id = ?' => $object->getId()],
        );

        return $this;
    }

    /**
     * Retrieve options searchable data
     *
     * @param int $productId
     * @param int $storeId
     * @return array
     */
    public function getSearchableData($productId, $storeId)
    {
        $adapter = $this->_getReadAdapter();

        $title = $adapter->getCheckSql(
            'option_title_store.title IS NOT NULL',
            'option_title_store.title',
            'option_title_default.title',
        );
        $bind = [
            'store_id'   => $storeId,
            'product_id' => $productId,
        ];
        $select = $adapter->select()
            ->from(['opt' => $this->getMainTable()], [])
            ->join(
                ['option_title_default' => $this->getTable('bundle/option_value')],
                'option_title_default.option_id = opt.option_id AND option_title_default.store_id = 0',
                [],
            )
            ->joinLeft(
                ['option_title_store' => $this->getTable('bundle/option_value')],
                'option_title_store.option_id = opt.option_id AND option_title_store.store_id = :store_id',
                ['title' => $title],
            )
            ->where('opt.parent_id=:product_id');
        if (!$searchData = $adapter->fetchCol($select, $bind)) {
            $searchData = [];
        }
        return $searchData;
    }
}
