<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

class Mage_Admin_Model_Resource_Roles_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('admin/role');
    }

    /**
     * @return $this
     */
    #[\Override]
    protected function _initSelect()
    {
        parent::_initSelect();

        $this->getSelect()->where('main_table.role_type = ?', 'G');

        return $this;
    }

    #[\Override]
    public function toOptionArray(): array
    {
        return $this->_toOptionArray('role_id', 'role_name');
    }
}
