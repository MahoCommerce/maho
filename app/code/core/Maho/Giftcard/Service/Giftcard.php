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
    /** The highest balance that an admin or an API client can give a card. A purchase has no limit. */
    public const MAX_BALANCE = 10000;

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
     * Save $giftcard. A new card needs a balance above zero and at most MAX_BALANCE. The model checks the
     * status, the code and the websites. An existing card changes its balance through adjustBalance().
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
            foreach (['initial_balance', 'balance'] as $field) {
                if ((float) $giftcard->getData($field) > self::MAX_BALANCE) {
                    $errors->addError($field, Mage::helper('giftcard')->__('Gift card balance cannot exceed %s', self::MAX_BALANCE));
                }
            }
            $errors->throwIfErrors();
        }
        $giftcard->save();
        return $giftcard;
    }

    /**
     * Give $giftcard the balance $newBalance and write a history entry with $comment.
     *
     * @throws Mage_Core_Exception_Input when the balance is below zero or above MAX_BALANCE
     */
    public function adjustBalance(Maho_Giftcard_Model_Giftcard $giftcard, float $newBalance, ?string $comment = null): Maho_Giftcard_Model_Giftcard
    {
        if ($newBalance < 0 || $newBalance > self::MAX_BALANCE) {
            throw new Mage_Core_Exception_Input()->addError('balance', $newBalance < 0
                ? Mage::helper('giftcard')->__('Gift card balance cannot be negative')
                : Mage::helper('giftcard')->__('Gift card balance cannot exceed %s', self::MAX_BALANCE));
        }
        if ($newBalance !== (float) $giftcard->getBalance()) {
            $giftcard->adjustBalance($newBalance, $comment);
        }
        return $giftcard;
    }

    /**
     * Change the balance and the status of $giftcard together: when one change fails, neither is saved.
     * An explicit status wins over the status that the new balance gives.
     *
     * @throws Mage_Core_Exception_Input
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
                $this->save($giftcard->setStatus($status));
            }
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
     * Email $giftcard to its recipient, once. A failed email does not fail the action that created the card.
     */
    public function sendToRecipient(Maho_Giftcard_Model_Giftcard $giftcard): void
    {
        if (!$giftcard->getRecipientEmail() || $giftcard->getEmailSentAt()) {
            return;
        }
        try {
            Mage::helper('giftcard')->sendGiftcardEmail($giftcard);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }
}
