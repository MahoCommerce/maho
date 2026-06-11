<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Catalog_TimeFormat
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '12h', 'label' => Mage::helper('adminhtml')->__('12h AM/PM')],
            ['value' => '24h', 'label' => Mage::helper('adminhtml')->__('24h')],
        ];
    }
}
