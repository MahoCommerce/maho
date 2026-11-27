<?php

/**
 * The Privacy tab of the customer page: the accepted terms and conditions and the privacy requests.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

class Maho_PrivacyCenter_Block_Adminhtml_Customer_Edit_Tab_Privacy extends Mage_Adminhtml_Block_Template implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected $_template = 'privacycenter/customer/tab.phtml';

    public function getCustomerId(): int
    {
        $customer = Mage::registry('current_customer');
        return $customer instanceof Mage_Customer_Model_Customer ? (int) $customer->getId() : 0;
    }

    /**
     * @return list<Maho_PrivacyCenter_Model_Request>
     */
    public function getPrivacyRequests(): array
    {
        return array_values(Mage::getService('privacycenter/request')->getForCustomer($this->getCustomerId())->getItems());
    }

    public function getAcceptances(): Mage_Checkout_Model_Resource_Agreement_Acceptance_Collection
    {
        return Mage::getService('checkout/agreement')->getAcceptances($this->getCustomerId());
    }

    public function getRequestUrl(Maho_PrivacyCenter_Model_Request $request): string
    {
        return $this->getUrl('*/customer_privacy/view', ['id' => $request->getId()]);
    }

    public function getCreateUrl(): string
    {
        return $this->getUrl('*/customer_privacy/create');
    }

    public function canCreate(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('customer/privacy/process');
    }

    public function formatDateTime(?string $utc): string
    {
        return $utc ? Mage::helper('core')->formatDate($utc, Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM, true) : '';
    }

    #[\Override]
    public function getTabLabel(): string
    {
        return Mage::helper('privacycenter')->__('Privacy');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return $this->getTabLabel();
    }

    #[\Override]
    public function canShowTab(): bool
    {
        return $this->getCustomerId() > 0 && Mage::getSingleton('admin/session')->isAllowed('customer/privacy/view');
    }

    #[\Override]
    public function isHidden(): bool
    {
        return false;
    }
}
