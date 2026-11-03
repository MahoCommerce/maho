<?php

/**
 * Subscribe and unsubscribe an address, for the storefront, the customer account, the admin and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

declare(strict_types=1);

use Mage_Newsletter_Model_Subscriber as Subscriber;

class Mage_Newsletter_Service_Subscriber
{
    public const XML_PATH_SUBSCRIBE_RATE_LIMIT = 'system/rate_limit/newsletter_subscribe';
    public const XML_PATH_UNSUBSCRIBE_RATE_LIMIT = 'system/rate_limit/newsletter_unsubscribe';

    public const RATE_LIMIT_WINDOW = 3600;

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Subscriber
    {
        $subscriber = Mage::getModel('newsletter/subscriber')->load($id);
        if (!$subscriber->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('newsletter')->__('This subscriber no longer exists.'));
        }
        return $subscriber;
    }

    /**
     * Return the subscriber of the customer $customerId. When the customer has none, return a new subscriber
     * that is not saved, with the email and the ID of the customer and the status unsubscribed.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the customer does not exist
     */
    public function getByCustomer(int $customerId): Subscriber
    {
        $customer = $this->getCustomer($customerId);
        $subscriber = Mage::getModel('newsletter/subscriber')->loadByCustomer($customer);
        if (!$subscriber->getId()) {
            $subscriber->setEmail($customer->getEmail())
                ->setCustomerId($customerId)
                ->setStatus(Subscriber::STATUS_UNSUBSCRIBED);
        }
        return $subscriber;
    }

    public function isGuestSubscriptionAllowed(int $storeId): bool
    {
        return Mage::getStoreConfigFlag(Subscriber::XML_PATH_ALLOW_GUEST_SUBSCRIBE_FLAG, $storeId);
    }

    /**
     * Return whether the answer to subscribe() tells the client to confirm the subscription by email.
     * A guest gets the answer of the store config, whatever the state of the address, so that the answer
     * does not show whether the address is subscribed.
     */
    public function isConfirmationPending(Subscriber $subscriber, ?int $customerId, int $storeId): bool
    {
        return $customerId === null
            ? Mage::getStoreConfigFlag(Subscriber::XML_PATH_CONFIRMATION_FLAG, $storeId)
            : $subscriber->getStatus() === Subscriber::STATUS_NOT_ACTIVE;
    }

    /**
     * Subscribe $email on the store $storeId. $customerId is the customer who is logged in, or null for a guest.
     * A customer who subscribes the address of the account needs no confirmation.
     * When the address is already subscribed, return its subscriber unchanged: getIsStatusChanged() is false.
     *
     * @throws Mage_Core_Exception_Input when the email is empty or not valid
     * @throws Mage_Core_Exception when the store does not accept guests and $customerId is null
     * @throws Mage_Core_Exception_TooManyRequests when the client or the address is over its limit
     */
    public function subscribe(#[\SensitiveParameter] string $email, int $storeId, ?int $customerId = null): Subscriber
    {
        $helper = Mage::helper('newsletter');
        $core = Mage::helper('core');
        if ($customerId === null && !$this->isGuestSubscriptionAllowed($storeId)) {
            throw new Mage_Core_Exception_Forbidden($helper->__('Guest subscription is not allowed. Please login first.'));
        }
        // The email validator accepts an empty value
        if (!$core->isValidNotBlank($email)) {
            throw new Mage_Core_Exception_Input()->addError('email', $helper->__('Email address is required'));
        }
        if (!$core->isValidEmail($email)) {
            throw new Mage_Core_Exception_Input()->addError('email', $helper->__('Invalid email address'));
        }

        // The limit of the client comes first: a client that sends many addresses gets past the limit of one address
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_SUBSCRIBE_RATE_LIMIT, $storeId);
        $this->attempt($core->rateLimiter('newsletter_subscribe', $limit, self::RATE_LIMIT_WINDOW, \Maho\Security\RateLimitScope::Ip));
        $this->attempt($core->rateLimiterBy('newsletter_subscribe', 'newsletter_subscribe:email:' . strtolower($email), $limit, self::RATE_LIMIT_WINDOW));

        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);
        if (!$subscriber->isSubscribed()) {
            $subscriber->subscribeEmail($email, $storeId, $customerId);
        }
        return $subscriber;
    }

    /**
     * Subscribe the address of the account of the customer $customerId.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the customer does not exist
     * @throws Mage_Core_Exception_TooManyRequests
     */
    public function subscribeCustomer(int $customerId, int $storeId): Subscriber
    {
        return $this->subscribe((string) $this->getCustomer($customerId)->getEmail(), $storeId, $customerId);
    }

    /**
     * Unsubscribe the subscriber $id. The link of an email gives the $code of the subscriber, the admin gives no code.
     * An unknown ID gets the error of a wrong code, so that the link does not show which IDs exist.
     *
     * @throws Mage_Core_Exception when $code is wrong
     * @throws Mage_Core_Exception_NoSuchEntity when the subscriber does not exist
     */
    public function unsubscribe(int $id, #[\SensitiveParameter] ?string $code = null): Subscriber
    {
        $subscriber = Mage::getModel('newsletter/subscriber')->load($id);
        if ($code !== null && (!$subscriber->getId() || !hash_equals((string) $subscriber->getCode(), $code))) {
            throw new Mage_Core_Exception(Mage::helper('newsletter')->__('Invalid subscription confirmation code.'));
        }
        if (!$subscriber->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('newsletter')->__('This subscriber no longer exists.'));
        }
        return $subscriber->unsubscribe();
    }

    /**
     * Unsubscribe the customer $customerId. When the customer has no subscription, return the subscriber of getByCustomer().
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the customer does not exist
     * @throws Mage_Core_Exception_TooManyRequests when the client is over its limit
     */
    public function unsubscribeCustomer(int $customerId): Subscriber
    {
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_UNSUBSCRIBE_RATE_LIMIT);
        $this->attempt(Mage::helper('core')->rateLimiter('newsletter_unsubscribe', $limit, self::RATE_LIMIT_WINDOW, \Maho\Security\RateLimitScope::Ip));

        $subscriber = $this->getByCustomer($customerId);
        return $subscriber->getId() ? $subscriber->unsubscribe() : $subscriber;
    }

    public function delete(Subscriber $subscriber): void
    {
        $subscriber->delete();
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    private function getCustomer(int $customerId): Mage_Customer_Model_Customer
    {
        /** @var Mage_Customer_Service_Customer $customers */
        $customers = Mage::getService('customer/customer');
        return $customers->getById($customerId)
            ?? throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('newsletter')->__('Customer not found'));
    }

    /**
     * @throws Mage_Core_Exception_TooManyRequests
     */
    private function attempt(\Maho\Security\RateLimiter $limiter): void
    {
        if (!$limiter->attempt()) {
            throw new Mage_Core_Exception_TooManyRequests(
                Mage::helper('newsletter')->__('Too many requests. Please try again later.'),
                self::RATE_LIMIT_WINDOW,
            );
        }
    }
}
