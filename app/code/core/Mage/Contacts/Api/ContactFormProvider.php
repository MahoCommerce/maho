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
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ContactForm
    {
        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();
        /** @var \Mage_Contacts_Service_Message $service */
        $service = \Mage::getService('contacts/message');

        $dto = new ContactForm();
        $dto->enabled = \Mage::helper('contacts')->isApiEnabled($storeId);
        $captcha = \Mage::helper('apiplatform')->getCaptchaConfig();
        $dto->captchaProvider = empty($captcha['enabled']) ? 'none' : (string) ($captcha['provider'] ?? 'none');
        $dto->captchaSiteKey = isset($captcha['site_key']) ? (string) $captcha['site_key'] : null;
        $dto->captchaChallengeUrl = isset($captcha['challenge_url']) ? (string) $captcha['challenge_url'] : null;
        $dto->honeypotField = $service->isHoneypotEnabled($storeId)
            ? \Mage::helper('core')->getHoneypotFieldName()
            : null;

        return $dto;
    }
}
