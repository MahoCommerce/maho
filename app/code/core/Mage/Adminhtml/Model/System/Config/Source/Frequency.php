<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
