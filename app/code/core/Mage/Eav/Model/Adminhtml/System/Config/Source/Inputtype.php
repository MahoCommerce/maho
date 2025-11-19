<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Eav_Model_Adminhtml_System_Config_Source_Inputtype
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'text', 'label' => Mage::helper('eav')->__('Text Field')],
            ['value' => 'textarea', 'label' => Mage::helper('eav')->__('Text Area')],
            ['value' => 'date', 'label' => Mage::helper('eav')->__('Date')],
            ['value' => 'boolean', 'label' => Mage::helper('eav')->__('Yes/No')],
            ['value' => 'multiselect', 'label' => Mage::helper('eav')->__('Multiple Select')],
            ['value' => 'select', 'label' => Mage::helper('eav')->__('Dropdown')],
        ];
    }
}
