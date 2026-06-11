<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

/**
 * @method Mage_Tax_Model_Class[] getItems()
 */
class Mage_Tax_Model_Resource_Class_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('tax/class');
    }

    /**
     * Add class type filter to result
     *
     * @param string $classTypeId
     * @return $this
     */
    public function setClassTypeFilter($classTypeId)
    {
        return $this->addFieldToFilter('main_table.class_type', $classTypeId);
    }

    #[\Override]
    public function toOptionArray(): array
    {
        return $this->_toOptionArray('class_id', 'class_name');
    }

    /**
     * Retrieve option hash
     *
     * @return array
     */
    #[\Override]
    public function toOptionHash()
    {
        return $this->_toOptionHash('class_id', 'class_name');
    }
}
