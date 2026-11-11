<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

declare(strict_types=1);

namespace Mage\Newsletter\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Give a subscribe or an unsubscribe request to the subscriber service, then shape its answer.
 */
final class NewsletterProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NewsletterSubscription
    {
        if (!$data instanceof NewsletterSubscription) {
            throw new BadRequestHttpException('Invalid request data');
        }

        return match ($operation->getName()) {
            'subscribe', 'subscribe_rest' => $this->subscribe($data, $context),
            'unsubscribe', 'unsubscribe_rest' => $this->unsubscribe(),
            default => throw new BadRequestHttpException('Invalid newsletter operation'),
        };
    }

    private function subscribe(NewsletterSubscription $data, array $context): NewsletterSubscription
    {
        $input = $context['args']['input'] ?? [];
        $storeId = $data->storeId ?? (isset($input['storeId']) ? (int) $input['storeId'] : null);
        if ($storeId !== null) {
            if (!isset(\Mage::app()->getStores()[$storeId])) {
                throw new \Mage_Core_Exception("Unknown store ID: {$storeId}");
            }
            // The listeners of the request check ?store= and X-Store-Code only, not a store ID in the body
            $user = $this->security?->getUser();
            $this->assertRequestedStoreAllowed($storeId, $user instanceof ApiUser ? $user : null);
        }
        $storeId = StoreContext::ensureStore($storeId);

        $service = \Mage::getService('newsletter/subscriber');
        $customerId = $this->getAuthenticatedCustomerId();
        $email = (string) ($data->email ?: ($input['email'] ?? ''));
        // The body of the client, for the CAPTCHA module
        $fields = ['captchaToken' => $data->captchaToken ?? $input['captchaToken'] ?? null];
        // A customer token that sends no email asks for the address of the account
        $subscriber = match (true) {
            $customerId !== null && $email === '' => $service->subscribeCustomer($customerId, $storeId),
            $customerId === null && $this->hasBackendAccess('newsletter') => $service->subscribeByAdmin($email, $storeId),
            default => $service->subscribe($email, $storeId, $customerId, $fields),
        };
        $confirmationPending = $service->isConfirmationPending($subscriber, $customerId, $storeId);
        $message = $confirmationPending
            ? 'A confirmation email has been sent. Please check your inbox.'
            : 'You have been successfully subscribed to the newsletter.';

        // Only the customer of the address gets the data of the subscriber, so that the answer does not show whether
        // an address of somebody else has an account or a subscription
        if (!$service->isAccountAddress((string) $subscriber->getSubscriberEmail(), $storeId, $customerId)) {
            $dto = new NewsletterSubscription();
            $dto->email = $email;
            $dto->message = $message;
            return $dto;
        }

        $dto = NewsletterSubscription::fromModel($subscriber);
        $dto->confirmationRequired = $confirmationPending;
        $dto->message = $subscriber->getIsStatusChanged() ? $message : 'You are already subscribed to the newsletter.';
        return $dto;
    }

    private function unsubscribe(): NewsletterSubscription
    {
        $this->requireUser();
        $customerId = $this->getAuthenticatedCustomerId()
            ?? throw new \Mage_Core_Exception_Forbidden('Authentication required to unsubscribe. Use the unsubscribe link in your email.');

        $subscriber = \Mage::getService('newsletter/subscriber')->unsubscribeCustomer($customerId);

        $dto = NewsletterSubscription::fromModel($subscriber);
        $dto->message = $subscriber->getId()
            ? 'You have been successfully unsubscribed from the newsletter.'
            : 'This email address is not subscribed to the newsletter.';
        return $dto;
    }
}
