<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api2
 */

/**
 * @deprecated since 26.7 Use Maho_ApiPlatform instead.
 */
class Mage_Api2_Model_Auth_User_Customer extends Mage_Api2_Model_Auth_User_Abstract
{
    /**
     * User type
     */
    public const USER_TYPE = 'customer';

    /**
     * Retrieve user human-readable label
     *
     * @return string
     */
    #[\Override]
    public function getLabel()
    {
        return Mage::helper('api2')->__('Customer');
    }

    /**
     * Retrieve user type
     *
     * @return string
     */
    #[\Override]
    public function getType()
    {
        return self::USER_TYPE;
    }

    /**
     * Retrieve user role
     *
     * @return int
     * @throws Mage_Api2_Exception
     */
    #[\Override]
    public function getRole()
    {
        if (!$this->_role) {
            // An OAuth token outlives the deactivation of its customer, so check the account on each request
            $customer = $this->_loadCustomer((int) $this->getUserId());
            if (!$customer->getId() || !$customer->getIsActive()) {
                throw new Mage_Api2_Exception('Customer is not active', Mage_Api2_Model_Server::HTTP_UNAUTHORIZED);
            }

            /** @var Mage_Api2_Model_Acl_Global_Role $role */
            $role = Mage::getModel('api2/acl_global_role')->load(Mage_Api2_Model_Acl_Global_Role::ROLE_CUSTOMER_ID);
            if (!$role->getId()) {
                throw new Exception('Customer role not found');
            }

            $this->_role = Mage_Api2_Model_Acl_Global_Role::ROLE_CUSTOMER_ID;
        }

        return $this->_role;
    }

    protected function _loadCustomer(int $customerId): Mage_Customer_Model_Customer
    {
        return Mage::getModel('customer/customer')->load($customerId);
    }
}
