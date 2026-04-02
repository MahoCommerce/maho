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

/**
 * Newsletter State Provider - Fetches newsletter subscription status
 */
final class NewsletterProvider extends \Maho\ApiPlatform\Provider
{
    #[\Override]
    protected function handleOperation(string $name, array $context, array $uriVariables): mixed
    {
        if ($name === 'newsletterStatus' || $name === 'status') {
            return $this->getSubscriptionStatus();
        }

        return null;
    }

    /**
     * Get subscription status for authenticated customer
     */
    private function getSubscriptionStatus(): NewsletterSubscription
    {
        $customerId = $this->requireAuthentication();

        $dto = new NewsletterSubscription();

        // Load customer to get email
        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            $dto->status = 'error';
            $dto->message = 'Customer not found';
            return $dto;
        }

        $dto->email = $customer->getEmail();
        $dto->customerId = $customerId;

        // Load subscriber by customer
        $subscriber = \Mage::getModel('newsletter/subscriber')->loadByCustomer($customer);

        if ($subscriber->getId()) {
            $dto->isSubscribed = $subscriber->isSubscribed();
            $dto->status = $this->mapSubscriberStatus($subscriber->getSubscriberStatus());
        } else {
            $dto->isSubscribed = false;
            $dto->status = 'unsubscribed';
        }

        return $dto;
    }

    /**
     * Map numeric subscriber status to string
     */
    private function mapSubscriberStatus(int $status): string
    {
        return match ($status) {
            \Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED => 'subscribed',
            \Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE => 'not_active',
            \Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED => 'unsubscribed',
            \Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED => 'unconfirmed',
            default => 'unknown',
        };
    }
}
