<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_GoogleAnalytics
 */

class Mage_GoogleAnalytics_Model_System_Config_Source_Type
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Mage_GoogleAnalytics_Helper_Data::TYPE_ANALYTICS4,
                'label' => Mage::helper('googleanalytics')->__('Google Analytics 4'),
            ],
        ];
    }
}
