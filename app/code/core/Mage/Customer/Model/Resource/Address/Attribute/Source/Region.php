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
    #[\Override]
    public function getAllOptions(bool $withEmpty = true, bool $defaultValues = false): array
    {
        if (!$this->_options) {
            $this->_options = Mage::getResourceModel('directory/region_collection')->load()->toOptionArray();
        }
        return $this->_options;
    }
}
