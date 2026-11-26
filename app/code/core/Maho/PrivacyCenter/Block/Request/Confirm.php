<?php

/**
 * The page of the confirmation link: it shows the request and posts the confirmation.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

class Maho_PrivacyCenter_Block_Request_Confirm extends Mage_Core_Block_Template
{
    public function getPrivacyRequest(): ?Maho_PrivacyCenter_Model_Request
    {
        $request = Mage::registry('privacy_request');
        return $request instanceof Maho_PrivacyCenter_Model_Request ? $request : null;
    }

    public function getToken(): string
    {
        return (string) Mage::registry('privacy_request_token');
    }

    public function getPostUrl(): string
    {
        return $this->getUrl('privacy/request/confirmPost', ['_secure' => true]);
    }

    public function getCoolingOffDays(): int
    {
        return Mage::helper('privacycenter')->getCoolingOffDays();
    }
}
