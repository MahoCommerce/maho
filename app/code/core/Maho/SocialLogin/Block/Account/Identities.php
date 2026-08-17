<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_Block_Account_Identities extends Mage_Core_Block_Template
{
    public function getIdentities(): Maho_SocialLogin_Model_Resource_Identity_Collection
    {
        /** @var Maho_SocialLogin_Model_Resource_Identity_Collection $collection */
        $collection = Mage::getResourceModel('sociallogin/identity_collection');
        return $collection
            ->addCustomerFilter((int) Mage::getSingleton('customer/session')->getCustomerId())
            ->setOrder('created_at', 'ASC');
    }

    public function getUnlinkUrl(): string
    {
        return Mage::getUrl('sociallogin/account/unlink');
    }

    public function getProviderLabel(string $code): string
    {
        return Mage::helper('sociallogin')->getProviderLabel($code);
    }
}
