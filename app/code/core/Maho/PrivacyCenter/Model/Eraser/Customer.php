<?php

/**
 * Deletes the customer account. The foreign keys delete its addresses, wishlist, alerts, segments and sign-in links, and keep its orders.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_PrivacyCenter_Model_Eraser_Customer extends Maho_PrivacyCenter_Model_Eraser_AbstractEraser
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('privacycenter')->__('Profile, address book, wishlist and saved payment methods');
    }

    #[\Override]
    public function erase(Subject $subject): string
    {
        $helper = Mage::helper('privacycenter');
        if ($subject->isGuest()) {
            return $helper->__('No customer account');
        }
        $customer = Mage::getModel('customer/customer')->load($subject->customerId);
        if (!$customer->getId()) {
            return $helper->__('No customer account');
        }

        $wasSecure = Mage::registry('isSecureArea');
        if (!$wasSecure) {
            Mage::register('isSecureArea', true);
        }
        try {
            Mage::getService('customer/customer')->delete($customer);
        } finally {
            if (!$wasSecure) {
                Mage::unregister('isSecureArea');
            }
        }
        return $helper->__('Customer account deleted');
    }
}
