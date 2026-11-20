<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
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

        $storeId = $object->getStoreId();
        $defaultTitleProvided = $object->hasData('default_title');
        $storeCondition = $defaultTitleProvided ? 'store_id = ? OR store_id = 0' : 'store_id = ?';
        $condition = [
            'option_id = ?' => $object->getId(),
            $storeCondition => $storeId,
        ];

        $write = $this->_getWriteAdapter();
        $table = $this->getTable('bundle/option_value');
        $write->delete($table, $condition);

        $data = new \Maho\DataObject();
        $data->setOptionId($object->getId())
            ->setStoreId($storeId)
            ->setTitle($object->getTitle());

        $write->insert($table, $data->getData());

        if ($storeId) {
            $data->setStoreId(0)->setTitle($object->getDefaultTitle());
            if ($defaultTitleProvided) {
                $write->insert($table, $data->getData());
            } else {
                // getSearchableData() skips an option that has no store 0 row.
                $write->insertIgnore($table, $data->getData());
            }
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
