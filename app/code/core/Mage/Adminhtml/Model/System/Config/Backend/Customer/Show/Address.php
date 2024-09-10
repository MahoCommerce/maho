<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer Show Address Model
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Model_System_Config_Backend_Customer_Show_Address extends Mage_Adminhtml_Model_System_Config_Backend_Customer_Show_Customer
{
    /**
     * Retrieve attribute objects
     *
     * @return array
     */
    #[\Override]
    protected function _getAttributeObjects()
    {
        $result = parent::_getAttributeObjects();
        $result[] = Mage::getSingleton('eav/config')->getAttribute('customer_address', $this->_getAttributeCode());
        return $result;
    }
}
