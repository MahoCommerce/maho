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
        $channel = \Mage_Contacts_Model_Channel::Api;

        $dto = new ContactForm();
        $dto->enabled = $service->isEnabled($channel, $storeId);
        $dto->captchaProvider = $service->getCaptchaProvider($channel, $storeId);
        $dto->captchaSiteKey = $service->getCaptchaSiteKey($channel, $storeId);
        $dto->honeypotField = $service->isHoneypotEnabled($channel, $storeId)
            ? \Mage::helper('core')->getHoneypotFieldName()
            : null;

        return $dto;
    }
}
