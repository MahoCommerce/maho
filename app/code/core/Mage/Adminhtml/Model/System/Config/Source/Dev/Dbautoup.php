<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Dev_Dbautoup
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Mage_Core_Model_Resource::AUTO_UPDATE_ALWAYS, 'label' => Mage::helper('adminhtml')->__('Always (during development)')],
            ['value' => Mage_Core_Model_Resource::AUTO_UPDATE_ONCE,   'label' => Mage::helper('adminhtml')->__('Only Once (version upgrade)')],
            ['value' => Mage_Core_Model_Resource::AUTO_UPDATE_NEVER,  'label' => Mage::helper('adminhtml')->__('Never (production)')],
        ];
    }
}
