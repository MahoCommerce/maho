<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

/**
 * @method $this setCreated(string $value)
 */
class Mage_Api_Model_Resource_Acl_Role extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('api/role', 'role_id');
    }

    /**
     * Action before save
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        if (!$object->getId()) {
            $this->setCreated(Mage::app()->getLocale()->formatDateForDb('now'));
        }
        return $this;
    }
}
