<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * System config email field backend model
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Model_System_Config_Backend_Email_Address extends Mage_Core_Model_Config_Data
{
    #[\Override]
    protected function _beforeSave()
    {
        $value = $this->getValue();
        if (!Zend_Validate::is($value, 'EmailAddress')) {
            Mage::throwException(Mage::helper('adminhtml')->__('Invalid email address "%s".', $value));
        }
        return $this;
    }
}
