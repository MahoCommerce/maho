<?php

/**
 * The public privacy request form, for a guest.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

class Maho_PrivacyCenter_Block_Request_Form extends Mage_Core_Block_Template
{
    public function getPostUrl(): string
    {
        return $this->getUrl('privacy/request/submit', ['_secure' => true]);
    }

    public function getLoginUrl(): string
    {
        return Mage::helper('customer')->getLoginUrl();
    }

    public function isGuestRequestAllowed(): bool
    {
        return Mage::helper('privacycenter')->isGuestRequestAllowed();
    }

    public function isHoneypotEnabled(): bool
    {
        return Mage::getStoreConfigFlag(Maho_PrivacyCenter_Helper_Data::XML_PATH_HONEYPOT_ENABLED);
    }

    /**
     * @return array<string, string>
     */
    public function getTypes(): array
    {
        return Mage::getModel('privacycenter/source_type')->toOptionHash();
    }

    public function getFormValue(string $field): string
    {
        $data = Mage::getSingleton('core/session')->getPrivacyRequestFormData();
        return is_array($data) && is_scalar($data[$field] ?? null) ? (string) $data[$field] : '';
    }

    #[\Override]
    protected function _afterToHtml($html)
    {
        Mage::getSingleton('core/session')->unsPrivacyRequestFormData();
        return parent::_afterToHtml($html);
    }
}
