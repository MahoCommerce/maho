<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

class Mage_Customer_Model_Resource_Address_Attribute_Source_Region extends Mage_Eav_Model_Entity_Attribute_Source_Table
{
    /**
     * Retrieve all region options
     *
     * @param bool $withEmpty       Argument has no effect, included for PHP 7.2 method signature compatibility
     * @param bool $defaultValues   Argument has no effect, included for PHP 7.2 method signature compatibility
     * @return array
     */
    #[\Override]
    public function getAllOptions($withEmpty = true, $defaultValues = false)
    {
        if (!$this->_options) {
            $this->_options = Mage::getResourceModel('directory/region_collection')->load()->toOptionArray();
        }
        return $this->_options;
    }
}
