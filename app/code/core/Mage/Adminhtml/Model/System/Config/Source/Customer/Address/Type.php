<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Source_Customer_Address_Type
{
    public function toOptionArray(): array
    {
        return [
            Mage_Customer_Model_Address_Abstract::TYPE_BILLING => Mage::helper('adminhtml')->__('Billing Address'),
            Mage_Customer_Model_Address_Abstract::TYPE_SHIPPING => Mage::helper('adminhtml')->__('Shipping Address'),
        ];
    }
}
