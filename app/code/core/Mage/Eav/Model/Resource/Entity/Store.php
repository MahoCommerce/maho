<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Resource_Entity_Store extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/entity_store', 'entity_store_id');
    }

    /**
     * Load an object by entity type and store
     *
     * @param int $entityTypeId
     * @param int $storeId
     * @return bool
     */
    public function loadByEntityStore(Mage_Core_Model_Abstract $object, $entityTypeId, $storeId)
    {
        $adapter = $this->_getWriteAdapter();
        $bind    = [
            ':entity_type_id' => $entityTypeId,
            ':store_id'       => $storeId,
        ];
        $select = $adapter->select()
            ->from($this->getMainTable())
            ->forUpdate(true)
            ->where('entity_type_id = :entity_type_id')
            ->where('store_id = :store_id');
        $data = $adapter->fetchRow($select, $bind);

        if (!$data) {
            return false;
        }

        $object->setData($data);

        $this->_afterLoad($object);

        return true;
    }
}
