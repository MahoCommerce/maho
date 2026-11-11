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
     * Return whether $email is the address of the account of the customer $customerId on the website of the store
     * $storeId. A guest has no account, so the answer is false for a null $customerId.
     */
    public function isAccountAddress(#[\SensitiveParameter] string $email, int $storeId, ?int $customerId): bool
    {
        return Mage::getModel('newsletter/subscriber')->isAccountEmail($email, $storeId, $customerId);
    }

    /**
     * Return whether the answer to subscribe() tells the client to confirm the subscription by email.
     * A guest gets the answer of the store config, and a customer who gives an address that is not the address of
     * the account always gets "confirm". These answers do not change with the state of the address, so that they
     * do not show whether the address is subscribed.
     */
    public function isConfirmationPending(Subscriber $subscriber, ?int $customerId, int $storeId): bool
    {
        if ($customerId === null) {
            return Mage::getStoreConfigFlag(Subscriber::XML_PATH_CONFIRMATION_FLAG, $storeId);
        }
        if (!$this->isAccountAddress((string) $subscriber->getSubscriberEmail(), $storeId, $customerId)) {
            return true;
        }
        return $subscriber->getStatus() === Subscriber::STATUS_NOT_ACTIVE;
    }

    /**
     * Subscribe $email on the store $storeId. $customerId is the customer who is logged in, or null for a guest.
     * A customer who gives the address of the account gets the result of subscribeCustomer(). A customer who gives
     * another address has no proof that the address is theirs: the guest switch applies, and the address always
     * gets the confirmation email, also when the store asks no confirmation.
     * When the address is already subscribed, return its subscriber unchanged: getIsStatusChanged() is false.
     *
     * @param array<array-key, mixed> $fields the fields that the client sent, with the token of the CAPTCHA: the form POST or the API body
     * @throws Mage_Core_Exception_Input when the email is empty or not valid, or when the CAPTCHA refuses the token
     * @throws Mage_Core_Exception_Forbidden when the store does not accept guests, and $customerId is null or $email
     *                                       is not the address of the account
     * @throws Mage_Core_Exception_TooManyRequests when the client, the address or the customer is over its limit
     */
    public function subscribe(#[\SensitiveParameter] string $email, int $storeId, ?int $customerId, array $fields): Subscriber
    {
        if ($customerId !== null && $this->isAccountAddress($email, $storeId, $customerId)) {
            return $this->subscribeAccountAddress($email, $storeId, $customerId);
        }
        $this->assertGuestSubscriptionAllowed($storeId, $customerId);
        Mage::helper('core')->assertCaptcha($fields);
        return $this->subscribeAddress($email, $storeId, $customerId);
    }

    /**
     * Subscribe $email on the store $storeId for an admin user, or for an API token with access to the newsletter.
     * An admin is not a bot, so this method asks no CAPTCHA. The guest switch and the limits of subscribe() apply.
     *
     * @throws Mage_Core_Exception_Input when the email is empty or not valid
     * @throws Mage_Core_Exception_Forbidden when the store does not accept guests
     * @throws Mage_Core_Exception_TooManyRequests when the client or the address is over its limit
     */
    public function subscribeByAdmin(#[\SensitiveParameter] string $email, int $storeId): Subscriber
    {
        $this->assertGuestSubscriptionAllowed($storeId, null);
        return $this->subscribeAddress($email, $storeId, null);
    }

    /**
     * Subscribe the address of the account of the customer $customerId. The customer is logged in and the address
     * is theirs, so there is no CAPTCHA and no confirmation, and the limit counts for the customer, not for the
     * IP address. When the customer is already subscribed, return the subscriber unchanged and count nothing.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the customer does not exist
     * @throws Mage_Core_Exception_TooManyRequests when the customer is over the limit
     */
    public function subscribeCustomer(int $customerId, int $storeId): Subscriber
    {
        return $this->subscribeAccountAddress((string) $this->getCustomer($customerId)->getEmail(), $storeId, $customerId);
    }

    /**
     * Confirm the subscriber $id with the $code of the confirmation email, and send the success email.
     * When the subscriber is already subscribed, return it unchanged: getIsStatusChanged() is false.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the subscriber does not exist or has no code
     * @throws Mage_Core_Exception when $code is wrong
     */
    public function confirm(int $id, #[\SensitiveParameter] string $code): Subscriber
    {
        $helper = Mage::helper('newsletter');
        $subscriber = Mage::getModel('newsletter/subscriber')->load($id);
        if ($subscriber->isSubscribed()) {
            return $subscriber;
        }
        if (!$subscriber->getId() || !$subscriber->getCode()) {
            throw new Mage_Core_Exception_NoSuchEntity($helper->__('Invalid subscription ID.'));
        }
        if (!$subscriber->confirm($code)) {
            throw new Mage_Core_Exception($helper->__('Invalid subscription confirmation code.'));
        }
        $subscriber->sendConfirmationSuccessEmail();
        return $subscriber;
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
     * Unsubscribe the customer $customerId. When the customer has no subscription, return the subscriber of
     * getByCustomer(). When the customer is already unsubscribed, return the subscriber unchanged: no save,
     * no email, and the limit counts nothing.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the customer does not exist
     * @throws Mage_Core_Exception_TooManyRequests when the client is over its limit
     */
    public function unsubscribeCustomer(int $customerId): Subscriber
    {
        $subscriber = $this->getByCustomer($customerId);
        if (!$subscriber->getId() || (int) $subscriber->getStatus() === Subscriber::STATUS_UNSUBSCRIBED) {
            return $subscriber;
        }
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_UNSUBSCRIBE_RATE_LIMIT);
        Mage::helper('core')->rateLimiter('newsletter_unsubscribe', $limit, self::RATE_LIMIT_WINDOW, \Maho\Security\RateLimitScope::Ip)
            ->attemptOrThrow();

        return $subscriber->unsubscribe();
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
        return $customers->getById($customerId);
    }

    /**
     * @throws Mage_Core_Exception_Forbidden
     */
    private function assertGuestSubscriptionAllowed(int $storeId, ?int $customerId): void
    {
        if ($this->isGuestSubscriptionAllowed($storeId)) {
            return;
        }
        $helper = Mage::helper('newsletter');
        throw new Mage_Core_Exception_Forbidden($customerId === null
            ? $helper->__('Guest subscription is not allowed. Please login first.')
            : $helper->__('You can subscribe only the email address of your account.'));
    }

    /**
     * Subscribe an address that is not the address of the account of the client.
     *
     * @throws Mage_Core_Exception_TooManyRequests
     */
    private function subscribeAddress(#[\SensitiveParameter] string $email, int $storeId, ?int $customerId): Subscriber
    {
        $core = Mage::helper('core');
        // The limit of the client comes first: a client that sends many addresses gets past the limit of one address
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_SUBSCRIBE_RATE_LIMIT, $storeId);
        $core->rateLimiter('newsletter_subscribe', $limit, self::RATE_LIMIT_WINDOW, \Maho\Security\RateLimitScope::Ip)
            ->attemptOrThrow();
        $core->rateLimiterBy('newsletter_subscribe', 'newsletter_subscribe:email:' . strtolower($email), $limit, self::RATE_LIMIT_WINDOW)
            ->attemptOrThrow();

        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);
        if (!$subscriber->isSubscribed()) {
            $subscriber->subscribeEmail($email, $storeId, $customerId, alwaysConfirm: $customerId !== null);
        }
        return $subscriber;
    }

    /**
     * @throws Mage_Core_Exception_TooManyRequests
     */
    private function subscribeAccountAddress(#[\SensitiveParameter] string $email, int $storeId, int $customerId): Subscriber
    {
        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);
        if ($subscriber->isSubscribed()) {
            return $subscriber;
        }
        Mage::helper('core')->rateLimiterBy(
            'newsletter_subscribe',
            "newsletter_subscribe:customer:{$customerId}",
            (int) Mage::getStoreConfig(self::XML_PATH_SUBSCRIBE_RATE_LIMIT, $storeId),
            self::RATE_LIMIT_WINDOW,
        )->attemptOrThrow();

        $subscriber->subscribeEmail($email, $storeId, $customerId, alwaysConfirm: true);
        return $subscriber;
    }
}
