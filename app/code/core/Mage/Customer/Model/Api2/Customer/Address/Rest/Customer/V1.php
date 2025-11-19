<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Customer_Model_Api2_Customer_Address_Rest_Customer_V1 extends Mage_Customer_Model_Api2_Customer_Address_Rest
{
    /**
     * Load customer address by id
     *
     * @param int $id
     * @throws Mage_Api2_Exception
     * @return Mage_Customer_Model_Address
     */
    #[\Override]
    protected function _loadCustomerAddressById($id)
    {
        $customerAddress = parent::_loadCustomerAddressById($id);
        // check owner
        if ($this->getApiUser()->getUserId() != $customerAddress->getCustomerId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        return $customerAddress;
    }

    /**
     * Load customer by id
     *
     * @param int $id
     * @throws Mage_Api2_Exception
     * @return Mage_Customer_Model_Customer
     */
    #[\Override]
    protected function _loadCustomerById($id)
    {
        $customer = parent::_loadCustomerById($id);
        // check customer accaunt owner
        if ($this->getApiUser()->getUserId() != $customer->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        return $customer;
    }
}
