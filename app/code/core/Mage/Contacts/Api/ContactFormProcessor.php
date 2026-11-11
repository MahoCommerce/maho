<?php

/**
 * Give a contact message to the contact message service. The service checks it and emails it to the store.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Contacts
 */

declare(strict_types=1);

namespace Mage\Contacts\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Http\RequestInput;
use Maho\ApiPlatform\Service\StoreContext;

class ContactFormProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ContactForm
    {
        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();
        if (!\Mage::helper('contacts')->isApiEnabled($storeId)) {
            throw new \Mage_Core_Exception_NoSuchEntity(\Mage::helper('contacts')->__('Contact form is not available'));
        }

        // The raw body, because the honeypot field has a name that the DTO does not know. Check the types of the fields that the service reads.
        $body = $this->parseRequestBody($context['request'] ?? null);
        $input = RequestInput::of($body);
        foreach (['name', 'email', 'comment', 'message', 'telephone', 'phone'] as $field) {
            $input->string($field);
        }
        \Mage::getService('contacts/message')->send($body, $storeId);

        $dto = new ContactForm();
        $dto->success = true;
        $dto->message = 'Your message has been sent. We will get back to you soon.';
        return $dto;
    }
}
