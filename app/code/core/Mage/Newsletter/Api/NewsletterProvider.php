<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

declare(strict_types=1);

namespace Mage\Newsletter\Api;

use Maho\ApiPlatform\CrudProvider;

/**
 * Newsletter Provider, extends CrudProvider with subscription status lookup.
 *
 * Handles the newsletterStatus and status named operations.
 */
final class NewsletterProvider extends CrudProvider
{
    #[\Override]
    protected function handleOperation(string $name, array $context, array $uriVariables): mixed
    {
        if ($name === 'newsletterStatus' || $name === 'status') {
            return $this->getSubscriptionStatus();
        }

        return null;
    }

    private function getSubscriptionStatus(): NewsletterSubscription
    {
        $customerId = $this->requireAuthentication();

        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            $dto = new NewsletterSubscription();
            $dto->status = 'error';
            $dto->message = 'Customer not found';
            return $dto;
        }

        $subscriber = \Mage::getModel('newsletter/subscriber')->loadByCustomer($customer);

        if ($subscriber->getId()) {
            $dto = NewsletterSubscription::fromModel($subscriber);
            $dto->email = $customer->getEmail();
            $dto->customerId = $customerId;
            return $dto;
        }

        $dto = new NewsletterSubscription();
        $dto->email = $customer->getEmail();
        $dto->customerId = $customerId;
        $dto->isSubscribed = false;
        $dto->status = 'unsubscribed';

        return $dto;
    }
}
