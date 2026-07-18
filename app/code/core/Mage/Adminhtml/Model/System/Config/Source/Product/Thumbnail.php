<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Product_Thumbnail
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'itself', 'label' => Mage::helper('adminhtml')->__('Product Thumbnail Itself')],
            ['value' => 'parent', 'label' => Mage::helper('adminhtml')->__('Parent Product Thumbnail')],
        ];
    }
}
