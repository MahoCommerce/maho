<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\Attestation\AuthenticatorData;

class Mage_Admin_Helper_Auth extends Mage_Core_Helper_Abstract
{
    public function getWebAuthn(): WebAuthn
    {
        return new WebAuthn(
            Mage::getStoreConfig('web/secure/name') ?: 'Maho',
            parse_url(Mage::getBaseUrl(), PHP_URL_HOST),
        );
    }

    public function getWebAuthnAttestationAuthenticatorData(#[\SensitiveParameter] string $authenticatorData): AuthenticatorData
    {
        return new AuthenticatorData($authenticatorData);
    }
}
