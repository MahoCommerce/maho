<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

class Mage_Customer_Model_Customer_Attribute_Source_Website extends Mage_Eav_Model_Entity_Attribute_Source_Table
{
    #[\Override]
    public function getAllOptions(bool $withEmpty = true, bool $defaultValues = false): array
    {
        if (!$this->_options) {
            $this->_options = Mage::getSingleton('adminhtml/system_store')->getWebsiteValuesForForm(true, true);
        }

        return $this->_options;
    }

    #[\Override]
    public function getOptionText($value)
    {
        if (!$this->_options) {
            $this->_options = $this->getAllOptions();
        }
        foreach ($this->_options as $option) {
            if ($option['value'] == $value) {
                return $option['label'];
            }
        }
        return false;
    }
}
