<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Newsletter
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Newsletter\Api;

use Maho\ApiPlatform\CrudProvider;

/**
 * Newsletter Provider — extends CrudProvider with subscription status lookup.
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
