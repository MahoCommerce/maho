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
 * Handles the `status` (GraphQL) and `status_rest` (REST) named operations.
 */
final class NewsletterProvider extends CrudProvider
{
    #[\Override]
    protected function handleOperation(string $name, array $context, array $uriVariables): mixed
    {
        if ($name === 'status' || $name === 'status_rest') {
            return $this->getSubscriptionStatus();
        }

        return null;
    }

    private function getSubscriptionStatus(): NewsletterSubscription
    {
        return NewsletterSubscription::fromModel(\Mage::getService('newsletter/subscriber')->getByCustomer($this->requireCustomerId()));
    }
}
