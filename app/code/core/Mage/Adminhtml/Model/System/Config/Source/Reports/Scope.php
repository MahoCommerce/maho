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

class Mage_Adminhtml_Model_System_Config_Source_Reports_Scope
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'website', 'label' => Mage::helper('adminhtml')->__('Website')],
            ['value' => 'group', 'label' => Mage::helper('adminhtml')->__('Store')],
            ['value' => 'store', 'label' => Mage::helper('adminhtml')->__('Store View')],
        ];
    }
}
