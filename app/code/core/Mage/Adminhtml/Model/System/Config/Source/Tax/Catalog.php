<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Tax_Catalog
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 0, 'label' => Mage::helper('adminhtml')->__('No (price without tax)')],
            ['value' => 1, 'label' => Mage::helper('adminhtml')->__('Yes (only price with tax)')],
            ['value' => 2, 'label' => Mage::helper('adminhtml')->__('Both (without and with tax)')],
        ];
    }
}
