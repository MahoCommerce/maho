<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\ApiResource\NewsletterSubscription;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Newsletter State Processor - Handles subscribe/unsubscribe operations
 *
 * @implements ProcessorInterface<NewsletterSubscription, NewsletterSubscription>
 */
final class NewsletterProcessor implements ProcessorInterface
{
    use AuthenticationTrait;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * Process newsletter subscription operations
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NewsletterSubscription
    {
        if (!$data instanceof NewsletterSubscription) {
            throw new BadRequestHttpException('Invalid request data');
        }

        $operationName = $operation->getName();

        // Handle GraphQL mutations
        if ($operationName === 'subscribeNewsletter' || $operationName === 'subscribe') {
            return $this->subscribe($data, $context);
        }

        if ($operationName === 'unsubscribeNewsletter' || $operationName === 'unsubscribe') {
            return $this->unsubscribe($data);
        }

        throw new BadRequestHttpException('Invalid newsletter operation');
    }

    /**
     * Subscribe to newsletter
     *
     * - Authenticated users: uses their account email and associates subscription
     * - Guests: requires email in request body
     */
    private function subscribe(NewsletterSubscription $data, array $context): NewsletterSubscription
    {
        $customerId = $this->getAuthenticatedCustomerId();
        $email = $data->email;

        // For authenticated users, use their email if not provided
        if ($customerId !== null) {
            $customer = \Mage::getModel('customer/customer')->load($customerId);
            if (!$customer->getId()) {
                throw new BadRequestHttpException('Customer not found');
            }

            // Use customer email if not explicitly provided
            if (empty($email)) {
                $email = $customer->getEmail();
            }

            $data->customerId = $customerId;
        }

        // For GraphQL, email might be in args
        if (empty($email) && isset($context['args']['email'])) {
            $email = $context['args']['email'];
        }

        // Validate email
        if (empty($email)) {
            throw new BadRequestHttpException('Email address is required');
        }

        $coreHelper = \Mage::helper('core');
        if (!$coreHelper->isValidEmail($email)) {
            throw new BadRequestHttpException('Invalid email address');
        }

        // Check if guest subscription is allowed
        if ($customerId === null) {
            $allowGuest = \Mage::getStoreConfigFlag(\Mage_Newsletter_Model_Subscriber::XML_PATH_ALLOW_GUEST_SUBSCRIBE_FLAG);
            if (!$allowGuest) {
                throw new BadRequestHttpException('Guest subscription is not allowed. Please login first.');
            }
        }

        $data->email = $email;

        try {
            /** @var \Mage_Newsletter_Model_Subscriber $subscriber */
            $subscriber = \Mage::getModel('newsletter/subscriber');

            // Check if already subscribed
            $subscriber->loadByEmail($email);
            if ($subscriber->getId() && $subscriber->getSubscriberStatus() == \Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) {
                $data->status = 'subscribed';
                $data->isSubscribed = true;
                $data->message = 'You are already subscribed to the newsletter.';
                return $data;
            }

            // Subscribe
            $status = $subscriber->subscribe($email);

            // Map status
            $data->status = $this->mapSubscriberStatus($status);
            $data->isSubscribed = ($status == \Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);

            // Check if confirmation is required
            $confirmRequired = \Mage::getStoreConfigFlag(\Mage_Newsletter_Model_Subscriber::XML_PATH_CONFIRMATION_FLAG);
            $data->confirmationRequired = $confirmRequired && !$data->isSubscribed;

            if ($data->confirmationRequired) {
                $data->message = 'A confirmation email has been sent. Please check your inbox.';
            } else {
                $data->message = 'You have been successfully subscribed to the newsletter.';
            }

            return $data;
        } catch (\Mage_Core_Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (\Exception $e) {
            \Mage::log('Newsletter subscription error: ' . $e->getMessage(), \Mage::LOG_ERROR);
            throw new BadRequestHttpException('An error occurred while processing your subscription.');
        }
    }

    /**
     * Unsubscribe from newsletter
     *
     * - Authenticated users: unsubscribes their account
     * - Guests: requires email in request body
     */
    private function unsubscribe(NewsletterSubscription $data): NewsletterSubscription
    {
        $customerId = $this->getAuthenticatedCustomerId();
        $email = $data->email;

        // For authenticated users, use their email if not provided
        if ($customerId !== null) {
            $customer = \Mage::getModel('customer/customer')->load($customerId);
            if (!$customer->getId()) {
                throw new BadRequestHttpException('Customer not found');
            }

            if (empty($email)) {
                $email = $customer->getEmail();
            }

            $data->customerId = $customerId;
        }

        // Validate email
        if (empty($email)) {
            throw new BadRequestHttpException('Email address is required');
        }

        $coreHelper = \Mage::helper('core');
        if (!$coreHelper->isValidEmail($email)) {
            throw new BadRequestHttpException('Invalid email address');
        }

        $data->email = $email;

        try {
            /** @var \Mage_Newsletter_Model_Subscriber $subscriber */
            $subscriber = \Mage::getModel('newsletter/subscriber');
            $subscriber->loadByEmail($email);

            if (!$subscriber->getId()) {
                $data->status = 'unsubscribed';
                $data->isSubscribed = false;
                $data->message = 'This email address is not subscribed to the newsletter.';
                return $data;
            }

            // For authenticated users unsubscribing their own email, skip code check
            if ($customerId !== null) {
                $subscriber->setCheckCode($subscriber->getCode());
            }

            $subscriber->unsubscribe();

            $data->status = 'unsubscribed';
            $data->isSubscribed = false;
            $data->message = 'You have been successfully unsubscribed from the newsletter.';

            return $data;
        } catch (\Mage_Core_Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (\Exception $e) {
            \Mage::log('Newsletter unsubscribe error: ' . $e->getMessage(), \Mage::LOG_ERROR);
            throw new BadRequestHttpException('An error occurred while processing your request.');
        }
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
