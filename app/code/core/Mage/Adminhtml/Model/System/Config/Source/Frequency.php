<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Frequency
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => Mage::helper('sitemap')->__('Do not show in sitemap')],
            ['value' => 'always', 'label' => Mage::helper('sitemap')->__('Always')],
            ['value' => 'hourly', 'label' => Mage::helper('sitemap')->__('Hourly')],
            ['value' => 'daily', 'label' => Mage::helper('sitemap')->__('Daily')],
            ['value' => 'weekly', 'label' => Mage::helper('sitemap')->__('Weekly')],
            ['value' => 'monthly', 'label' => Mage::helper('sitemap')->__('Monthly')],
            ['value' => 'yearly', 'label' => Mage::helper('sitemap')->__('Yearly')],
            ['value' => 'never', 'label' => Mage::helper('sitemap')->__('Never')],
        ];
    }
}
