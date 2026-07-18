<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

class Mage_Dataflow_Model_Resource_Profile extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('dataflow/profile', 'profile_id');
    }

    /**
     * Setting up created_at and updarted_at
     */
    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        if (!$object->getCreatedAt()) {
            $object->setCreatedAt(Mage::app()->getLocale()->formatDateForDb('now'));
        }
        $object->setUpdatedAt(Mage::app()->getLocale()->formatDateForDb('now'));
        return parent::_beforeSave($object);
    }

    /**
     * Returns true if profile with name exists
     *
     * @param string $name
     * @param int $id
     * @return bool
     */
    public function isProfileExists($name, $id = null)
    {
        $bind = ['name' => $name];
        $select = $this->_getReadAdapter()->select();
        $select
            ->from($this->getMainTable(), 'count(1)')
            ->where('name = :name');
        if ($id) {
            $select->where("{$this->getIdFieldName()} != :id");
            $bind['id'] = $id;
        }
        return $this->_getReadAdapter()->fetchOne($select, $bind) ? true : false;
    }
}
