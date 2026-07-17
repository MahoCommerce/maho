<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

class Mage_Tax_Model_Resource_Class extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('tax/tax_class', 'class_id');
    }

    /**
     * Initialize unique fields
     *
     * @return $this
     */
    #[\Override]
    protected function _initUniqueFields()
    {
        $this->_uniqueFields = [[
            'field' => ['class_type', 'class_name'],
            'title' => Mage::helper('tax')->__('An error occurred while saving this tax class. A class with the same name'),
        ]];
        return $this;
    }
}
