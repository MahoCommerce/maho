<?php

/**
 * Load, check, save and delete the addresses of customers, for the storefront, the admin, the API and the CLI.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

use Mage_Customer_Model_Address as Address;

class Mage_Customer_Service_Address
{
    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Address
    {
        $address = Mage::getModel('customer/address')->load($id);
        if (!$address->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('customer')->__('Address not found'));
        }
        return $address;
    }

    /**
     * Return the address $id of the customer $customerId. The address of another customer gets the error of an
     * address that does not exist, so that the answer does not show which IDs exist.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getByCustomer(int $id, int $customerId): Address
    {
        $address = $this->getById($id);
        if ((int) $address->getCustomerId() !== $customerId) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('customer')->__('Address not found'));
        }
        return $address;
    }

    /**
     * Check $address and save it. The transport puts its input into the address first, with the customer ID.
     * getIsDefaultBilling() and getIsDefaultShipping() say what to do with the default addresses of the customer:
     * true makes the address the default, false removes it from the default, and null keeps the default as it is.
     * The first address of a customer always becomes the default billing and shipping address.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the customer does not exist
     * @throws Mage_Core_Exception_Input with one error for each problem of the address
     */
    public function save(Address $address): Address
    {
        /** @var Mage_Customer_Service_Customer $customers */
        $customers = Mage::getService('customer/customer');
        $customer = $customers->getById((int) $address->getCustomerId());

        Mage_Core_Exception_Input::assertValid($address->validate(), 'address');

        if (!$address->getId() && $customer->getAddresses() === []) {
            $address->setIsDefaultBilling(true)->setIsDefaultShipping(true);
        }
        $address->save();

        $clear = [];
        foreach (['default_billing' => $address->getIsDefaultBilling(), 'default_shipping' => $address->getIsDefaultShipping()] as $code => $isDefault) {
            if ($isDefault === false && (int) $customer->getData($code) === (int) $address->getId()) {
                $clear[] = $code;
            }
        }
        foreach ($clear as $code) {
            $customer->setData($code)->getResource()->saveAttribute($customer, $code);
        }
        return $address;
    }

    /**
     * Delete $address. The resource model removes it from the default addresses of the customer.
     */
    public function delete(Address $address): void
    {
        $address->delete();
    }
}
