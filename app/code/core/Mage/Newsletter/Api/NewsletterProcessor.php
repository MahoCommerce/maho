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

use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Newsletter Processor — handles subscribe/unsubscribe operations.
 *
 * Newsletter has custom subscribe/unsubscribe flows that don't map to standard CRUD,
 * so this extends the base Processor. It uses CrudResource::fromModel() for responses
 * where a subscriber model is available.
 */
final class NewsletterProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NewsletterSubscription
    {
        if (!$data instanceof NewsletterSubscription) {
            throw new BadRequestHttpException('Invalid request data');
        }

        $operationName = $operation->getName();

        if ($operationName === 'subscribeNewsletter' || $operationName === 'subscribe') {
            return $this->subscribe($data, $context);
        }

        if ($operationName === 'unsubscribeNewsletter' || $operationName === 'unsubscribe') {
            return $this->unsubscribe($data);
        }

        throw new BadRequestHttpException('Invalid newsletter operation');
    }

    private function subscribe(NewsletterSubscription $data, array $context): NewsletterSubscription
    {
        $customerId = $this->getAuthenticatedCustomerId();
        $email = $data->email;

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

        if (empty($email) && isset($context['args']['input']['email'])) {
            $email = $context['args']['input']['email'];
        }

        if (empty($email)) {
            throw new BadRequestHttpException('Email address is required');
        }

        $coreHelper = \Mage::helper('core');
        if (!$coreHelper->isValidEmail($email)) {
            throw new BadRequestHttpException('Invalid email address');
        }

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

            $subscriber->loadByEmail($email);
            if ($subscriber->getId() && $subscriber->getSubscriberStatus() == \Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) {
                $dto = NewsletterSubscription::fromModel($subscriber);
                $dto->message = 'You are already subscribed to the newsletter.';
                return $dto;
            }

            $status = $subscriber->subscribe($email);

            $subscriber->loadByEmail($email);
            $dto = NewsletterSubscription::fromModel($subscriber);

            $confirmRequired = \Mage::getStoreConfigFlag(\Mage_Newsletter_Model_Subscriber::XML_PATH_CONFIRMATION_FLAG);
            $dto->confirmationRequired = $confirmRequired && !$dto->isSubscribed;

            if ($dto->confirmationRequired) {
                $dto->message = 'A confirmation email has been sent. Please check your inbox.';
            } else {
                $dto->message = 'You have been successfully subscribed to the newsletter.';
            }

            return $dto;
        } catch (\Mage_Core_Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (\Exception $e) {
            \Mage::log('Newsletter subscription error: ' . $e->getMessage(), \Mage::LOG_ERROR);
            throw new BadRequestHttpException('An error occurred while processing your subscription.');
        }
    }

    private function unsubscribe(NewsletterSubscription $data): NewsletterSubscription
    {
        $customerId = $this->getAuthenticatedCustomerId();

        if ($customerId === null) {
            throw new AccessDeniedHttpException(
                'Authentication required to unsubscribe. Use the unsubscribe link in your email.',
            );
        }

        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            throw new BadRequestHttpException('Customer not found');
        }

        $email = $customer->getEmail();

        try {
            /** @var \Mage_Newsletter_Model_Subscriber $subscriber */
            $subscriber = \Mage::getModel('newsletter/subscriber');
            $subscriber->loadByEmail($email);

            if (!$subscriber->getId()) {
                $dto = new NewsletterSubscription();
                $dto->email = $email;
                $dto->customerId = $customerId;
                $dto->status = 'unsubscribed';
                $dto->isSubscribed = false;
                $dto->message = 'This email address is not subscribed to the newsletter.';
                return $dto;
            }

            $subscriber->setCheckCode($subscriber->getCode());
            $subscriber->unsubscribe();

            $dto = NewsletterSubscription::fromModel($subscriber);
            $dto->message = 'You have been successfully unsubscribed from the newsletter.';

            return $dto;
        } catch (\Mage_Core_Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (\Exception $e) {
            \Mage::log('Newsletter unsubscribe error: ' . $e->getMessage(), \Mage::LOG_ERROR);
            throw new BadRequestHttpException('An error occurred while processing your request.');
        }
    }
}
