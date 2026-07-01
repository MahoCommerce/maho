<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Contacts
 */

declare(strict_types=1);

namespace Mage\Contacts\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Service\StoreContext;

class ContactFormProvider extends \Maho\ApiPlatform\Provider
{
    private const CONFIG_ENABLED = 'contacts/api/enabled';
    private const CONFIG_CAPTCHA_PROVIDER = 'contacts/api/captcha_provider';
    private const CONFIG_CAPTCHA_SITE_KEY = 'contacts/api/captcha_site_key';
    private const CONFIG_HONEYPOT = 'contacts/api/honeypot_enabled';

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ContactForm
    {
        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();

        $provider = \Mage::getStoreConfig(self::CONFIG_CAPTCHA_PROVIDER, $storeId) ?: 'none';

        $dto = new ContactForm();
        $dto->enabled = (bool) \Mage::getStoreConfigFlag(self::CONFIG_ENABLED, $storeId);
        $dto->captchaProvider = $provider;
        $dto->captchaSiteKey = $provider !== 'none'
            ? \Mage::getStoreConfig(self::CONFIG_CAPTCHA_SITE_KEY, $storeId)
            : null;
        $dto->honeypotField = \Mage::getStoreConfigFlag(self::CONFIG_HONEYPOT, $storeId)
            ? \Mage::helper('core')->getHoneypotFieldName()
            : null;

        return $dto;
    }
}
