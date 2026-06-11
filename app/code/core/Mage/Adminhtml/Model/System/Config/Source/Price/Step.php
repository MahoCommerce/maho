<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Price_Step
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Mage_Catalog_Model_Layer_Filter_Price::RANGE_CALCULATION_AUTO,
                'label' => Mage::helper('adminhtml')->__('Automatic (equalize price ranges)'),
            ],
            [
                'value' => Mage_Catalog_Model_Layer_Filter_Price::RANGE_CALCULATION_IMPROVED,
                'label' => Mage::helper('adminhtml')->__('Automatic (equalize product counts)'),
            ],
            [
                'value' => Mage_Catalog_Model_Layer_Filter_Price::RANGE_CALCULATION_MANUAL,
                'label' => Mage::helper('adminhtml')->__('Manual'),
            ],
            [
                'value' => Mage_Catalog_Model_Layer_Filter_Price::RANGE_CALCULATION_INPUT,
                'label' => Mage::helper('adminhtml')->__('Input fields (from - to)'),
            ],
        ];
    }
}
