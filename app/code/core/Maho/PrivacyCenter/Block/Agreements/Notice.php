<?php

/**
 * A notice in the customer account when terms and conditions wait for the acceptance of the customer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

class Maho_PrivacyCenter_Block_Agreements_Notice extends Mage_Core_Block_Template
{
    public function getPendingCount(): int
    {
        $session = Mage::getSingleton('customer/session');
        if (!$session->isLoggedIn()) {
            return 0;
        }
        return count(Mage::getService('checkout/agreement')->getPendingForCustomer(
            $session->getCustomer(),
            (int) Mage::app()->getStore()->getId(),
        ));
    }

    public function getAgreementsUrl(): string
    {
        return $this->getUrl('privacy/account/agreements', ['_secure' => true]);
    }
}
