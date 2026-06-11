<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

declare(strict_types=1);

class Mage_Api_Model_Resource_Rules_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Resource collection initialization
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('api/rules');
    }

    /**
     * Retrieve rules by role
     *
     * @param string $id
     * @return $this
     */
    public function getByRoles($id)
    {
        $this->getSelect()->where('role_id = ?', (int) $id);
        return $this;
    }

    /**
     * Add sort by length
     *
     * @return $this
     */
    public function addSortByLength()
    {
        $this->getSelect()->columns(['length' => $this->getConnection()->getLengthSql('resource_id')])
            ->order('length DESC');
        return $this;
    }
}
