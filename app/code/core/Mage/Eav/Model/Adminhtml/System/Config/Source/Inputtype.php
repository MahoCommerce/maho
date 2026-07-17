<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Adminhtml_System_Config_Source_Inputtype
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'text', 'label' => Mage::helper('eav')->__('Text Field')],
            ['value' => 'textarea', 'label' => Mage::helper('eav')->__('Text Area')],
            ['value' => 'date', 'label' => Mage::helper('eav')->__('Date')],
            ['value' => 'boolean', 'label' => Mage::helper('eav')->__('Yes/No')],
            ['value' => 'multiselect', 'label' => Mage::helper('eav')->__('Multiple Select')],
            ['value' => 'select', 'label' => Mage::helper('eav')->__('Dropdown')],
        ];
    }
}
