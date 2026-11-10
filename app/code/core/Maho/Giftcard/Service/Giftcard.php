<?php

/**
 * Load, save and check gift cards, and change their balance, for the admin pages, the storefront and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

class Maho_Giftcard_Service_Giftcard
{
    public const XML_PATH_BALANCE_RATE_LIMIT = 'system/rate_limit/giftcard_balance';
    public const BALANCE_RATE_LIMIT_WINDOW = 60;

    /** The failed lookups of one customer that the balance check accepts in CUSTOMER_FAILURE_WINDOW seconds. */
    public const CUSTOMER_FAILURE_LIMIT = 10;
    public const CUSTOMER_FAILURE_WINDOW = 3600;

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Maho_Giftcard_Model_Giftcard
    {
        /** @var Maho_Giftcard_Model_Giftcard $giftcard */
        $giftcard = Mage::getModel('giftcard/giftcard');
        if ($id > 0) {
            $giftcard->load($id);
        }
        if (!$giftcard->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('giftcard')->__('This gift card no longer exists.'));
        }
        return $giftcard;
    }

    /**
     * Load the card of $code. The case of the code does not matter. With $websiteId, a card of
     * another website does not exist for the caller.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getByCode(string $code, ?int $websiteId = null): Maho_Giftcard_Model_Giftcard
    {
        /** @var Maho_Giftcard_Model_Giftcard $giftcard */
        $giftcard = Mage::getModel('giftcard/giftcard');
        if (trim($code) !== '') {
            $giftcard->loadByCode(trim($code));
        }
        if (!$giftcard->getId() || ($websiteId !== null && !$giftcard->isAvailableOnWebsite($websiteId))) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('giftcard')->__('Gift card not found.'));
        }
        return $giftcard;
    }

    /**
     * Return the card of $code when a shopper of $websiteId can spend it. An unknown code, a card of
     * another website and a card that is not valid get the same error, so that a client cannot find
     * the codes that exist. A code lookup is a brute force target: each client has a limit per minute,
     * and the failed lookups of the customer $customerId have a limit per hour.
     *
     * @throws Mage_Core_Exception_Input when the code is empty
     * @throws Mage_Core_Exception_TooManyRequests
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function checkBalance(string $code, int $websiteId, ?int $customerId = null): Maho_Giftcard_Model_Giftcard
    {
        $helper = Mage::helper('giftcard');
        $core = Mage::helper('core');
        $failures = $customerId ? $core->rateLimiterBy('giftcard_balance_check', (string) $customerId, self::CUSTOMER_FAILURE_LIMIT, self::CUSTOMER_FAILURE_WINDOW) : null;
        if ($failures?->tooManyAttempts()) {
            throw new Mage_Core_Exception_TooManyRequests($helper->__('Too many attempts. Please wait a moment and try again.'), self::CUSTOMER_FAILURE_WINDOW);
        }
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_BALANCE_RATE_LIMIT);
        if (!$core->rateLimiter('giftcard_balance', $limit, self::BALANCE_RATE_LIMIT_WINDOW)->attempt()) {
            throw new Mage_Core_Exception_TooManyRequests($helper->__('Too many attempts. Please wait a moment and try again.'), self::BALANCE_RATE_LIMIT_WINDOW);
        }
        if (trim($code) === '') {
            throw new Mage_Core_Exception_Input()->addError('code', $helper->__('Please enter a gift card code.'));
        }

        try {
            $giftcard = $this->getByCode($code, $websiteId);
            if (!$giftcard->isValid()) {
                throw new Mage_Core_Exception_NoSuchEntity($helper->__('Gift card not found.'));
            }
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            $failures?->hit();
            throw $e;
        }
        return $giftcard;
    }

    /**
     * Save $giftcard. A new card needs a balance above zero. The model checks the status, the code, the
     * websites and the highest balance. An existing card changes its balance through adjustBalance().
     *
     * @throws Mage_Core_Exception_Input
     * @throws Mage_Core_Exception_Conflict when another card has the code
     */
    public function save(Maho_Giftcard_Model_Giftcard $giftcard): Maho_Giftcard_Model_Giftcard
    {
        if (!$giftcard->getId()) {
            $balance = (float) ($giftcard->getData('initial_balance') ?: $giftcard->getData('balance'));
            $errors = new Mage_Core_Exception_Input();
            if ($balance <= 0) {
                $errors->addError('initial_balance', Mage::helper('giftcard')->__('Gift card balance must be greater than zero'));
            }
            $errors->throwIfErrors();
        }
        $giftcard->save();
        return $giftcard;
    }

    /**
     * Give $giftcard the balance $newBalance and write a history entry with $comment.
     *
     * @throws Mage_Core_Exception_Input when the model refuses the balance: below zero or above the highest balance
     */
    public function adjustBalance(Maho_Giftcard_Model_Giftcard $giftcard, float $newBalance, ?string $comment = null): Maho_Giftcard_Model_Giftcard
    {
        if ($newBalance !== (float) $giftcard->getBalance()) {
            $giftcard->adjustBalance($newBalance, $comment);
        }
        return $giftcard;
    }

    /**
     * Save the balance, the status and the other changes of the existing card $giftcard together: when one
     * change fails, nothing is saved. An explicit status wins over the status that the new balance gives.
     *
     * @throws Mage_Core_Exception_Input
     * @throws Mage_Core_Exception_Conflict when another card has the code
     */
    public function update(Maho_Giftcard_Model_Giftcard $giftcard, ?float $balance, ?string $status, ?string $comment = null): Maho_Giftcard_Model_Giftcard
    {
        $resource = $giftcard->getResource();
        $resource->beginTransaction();
        try {
            if ($balance !== null) {
                $this->adjustBalance($giftcard, $balance, $comment);
            }
            if ($status !== null && $status !== $giftcard->getStatus()) {
                $giftcard->setStatus($status);
            }
            $this->save($giftcard);
            $resource->commit();
        } catch (Throwable $e) {
            $resource->rollBack();
            throw $e;
        }
        return $giftcard;
    }

    public function delete(Maho_Giftcard_Model_Giftcard $giftcard): void
    {
        $giftcard->delete();
    }

    /**
     * Email $giftcard to its recipient now, also when the card got the email before, and record the time in
     * email_sent_at. The admin Send Email button and the API use this method.
     *
     * @throws Mage_Core_Exception_Input when the card has no recipient email
     * @throws Mage_Core_Exception when the store cannot send the email
     */
    public function sendEmail(Maho_Giftcard_Model_Giftcard $giftcard, ?int $storeId = null): Maho_Giftcard_Model_Giftcard
    {
        if (!$giftcard->getRecipientEmail()) {
            throw new Mage_Core_Exception_Input()->addError('recipient_email', Mage::helper('giftcard')->__('No recipient email address set.'));
        }
        Mage::helper('giftcard')->sendGiftcardEmail($giftcard, $storeId);
        return $giftcard;
    }

    /**
     * Email $giftcard to its recipient, once, with the settings of the store $storeId or of the current store.
     * A $deliveryDate (Y-m-d) after today in the timezone of that store schedules the email for the start of that
     * day, and sendScheduledEmails() sends it then. Today, a past date, a date that is not valid or no date sends
     * the email now. A failed email does not fail the action that created the card.
     */
    public function sendToRecipient(Maho_Giftcard_Model_Giftcard $giftcard, ?int $storeId = null, ?string $deliveryDate = null): void
    {
        if (!$giftcard->getRecipientEmail() || $giftcard->getEmailSentAt()) {
            return;
        }
        $scheduledAt = $this->getScheduledTime($deliveryDate, $storeId);
        if ($scheduledAt !== null) {
            Mage::helper('giftcard')->scheduleGiftcardEmail($giftcard, DateTime::createFromImmutable($scheduledAt));
            return;
        }
        try {
            Mage::helper('giftcard')->sendGiftcardEmail($giftcard, $storeId);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Email each card whose scheduled time has come and that has no email yet, with the settings of the store of
     * its order. A card whose email fails stays scheduled, so the next call tries it again.
     */
    public function sendScheduledEmails(int $limit = 50): void
    {
        $collection = Mage::getResourceModel('giftcard/giftcard_collection')
            ->addFieldToFilter('email_scheduled_at', ['lteq' => Mage::app()->getLocale()->formatDateForDb('now')])
            ->addFieldToFilter('email_sent_at', ['null' => true])
            ->addFieldToFilter('recipient_email', ['neq' => ''])
            ->setOrder('email_scheduled_at', Maho\Data\Collection::SORT_ORDER_ASC)
            ->setPageSize($limit);
        $collection->getSelect()->joinLeft(
            ['purchase_order' => $collection->getTable('sales/order')],
            'purchase_order.entity_id = main_table.purchase_order_id',
            ['purchase_store_id' => 'store_id'],
        );

        /** @var Maho_Giftcard_Model_Giftcard $giftcard */
        foreach ($collection as $giftcard) {
            $storeId = $giftcard->getData('purchase_store_id');
            $this->sendToRecipient($giftcard, $storeId === null ? null : (int) $storeId);
        }
    }

    /**
     * Return the start of $deliveryDate in the timezone of the store $storeId, in UTC. Return null when the date
     * is not valid or is not after today in that timezone.
     */
    private function getScheduledTime(?string $deliveryDate, ?int $storeId): ?DateTimeImmutable
    {
        if ($deliveryDate === null || !Mage::helper('giftcard')->isValidDeliveryDate($deliveryDate)) {
            return null;
        }
        $locale = Mage::app()->getLocale();
        if ($deliveryDate <= $locale->utcToStore($storeId)->format(Mage_Core_Model_Locale::DATE_FORMAT)) {
            return null;
        }
        return $locale->storeToUtc($storeId, $deliveryDate . ' 00:00:00');
    }
}
