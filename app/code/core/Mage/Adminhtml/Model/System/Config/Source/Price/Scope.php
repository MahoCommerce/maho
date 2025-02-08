<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Model_System_Config_Source_Price_Scope
{
    public function toOptionArray()
    {
        return [
            ['value' => '0', 'label' => Mage::helper('core')->__('Global')],
            ['value' => '1', 'label' => Mage::helper('core')->__('Website')],
        ];
    }
}
