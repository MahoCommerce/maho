<?php

/**
 * "Social Login" tab on the admin customer edit page.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_Block_Adminhtml_Customer_Edit_Tab_Identities extends Mage_Adminhtml_Block_Template implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected $_template = 'sociallogin/customer/identities.phtml';

    #[\Override]
    public function getTabLabel(): string
    {
        return $this->__('Social Login');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return $this->__('Social Login');
    }

    #[\Override]
    public function canShowTab(): bool
    {
        return (bool) $this->getCustomerId();
    }

    #[\Override]
    public function isHidden(): bool
    {
        return false;
    }

    public function getCustomerId(): int
    {
        return (int) Mage::registry('current_customer')?->getId();
    }

    public function getIdentities(): Maho_SocialLogin_Model_Resource_Identity_Collection
    {
        /** @var Maho_SocialLogin_Model_Resource_Identity_Collection $collection */
        $collection = Mage::getResourceModel('sociallogin/identity_collection');
        return $collection
            ->addCustomerFilter($this->getCustomerId())
            ->setOrder('created_at', 'ASC');
    }

    public function getUnlinkUrl(): string
    {
        return $this->getUrl('*/sociallogin_customer/unlink');
    }
}
