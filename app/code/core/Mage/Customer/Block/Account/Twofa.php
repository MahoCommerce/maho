<?php

/**
 * Customer account two-factor authentication management block.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

class Mage_Customer_Block_Account_Twofa extends Mage_Core_Block_Template
{
    /**
     * Whether 2FA is currently enabled for the logged-in customer
     */
    public function isEnabled(): bool
    {
        return (bool) $this->getCustomer()->getTwofaEnabled();
    }

    /**
     * Whether the merchant forces all customers to use 2FA
     */
    public function isRequired(): bool
    {
        return Mage::getStoreConfigFlag('customer/password/require_2fa');
    }

    /**
     * Logged-in customer
     */
    public function getCustomer(): Mage_Customer_Model_Customer
    {
        return Mage::getSingleton('customer/session')->getCustomer();
    }

    /**
     * Build the QR code for the customer, lazily generating and persisting a secret if needed
     */
    public function getQrCode(): string
    {
        $customer = $this->getCustomer();
        if (!$customer->getTwofaSecret()) {
            $secret = Mage::helper('core/security')->generateTotpSecret();
            $customer->setTwofaSecret($secret)->save();
        }

        return Mage::helper('core/security')->getTotpQrCode((string) $customer->getEmail(), (string) $customer->getTwofaSecret());
    }

    /**
     * Form submission URL
     */
    public function getFormAction(): string
    {
        return $this->getUrl('customer/account/twofaPost');
    }
}
