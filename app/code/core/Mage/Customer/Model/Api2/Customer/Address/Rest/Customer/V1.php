<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
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
