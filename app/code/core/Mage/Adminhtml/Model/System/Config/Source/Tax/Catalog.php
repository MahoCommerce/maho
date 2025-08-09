<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
