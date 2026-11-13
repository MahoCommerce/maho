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
        $giftcard = Mage::getModel('giftcard/giftcard')->load($id);
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
        $tooManyAttempts = $helper->__('Too many attempts. Please wait a moment and try again.');
        $failures = $customerId ? $core->rateLimiterBy('giftcard_balance_check', (string) $customerId, self::CUSTOMER_FAILURE_LIMIT, self::CUSTOMER_FAILURE_WINDOW) : null;
        $failures?->throwIfTooManyAttempts($tooManyAttempts);
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_BALANCE_RATE_LIMIT);
        $core->rateLimiter('giftcard_balance', $limit, self::BALANCE_RATE_LIMIT_WINDOW)->attemptOrThrow($tooManyAttempts);
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
     * Save $giftcard. The model checks the status, the code, the websites and the balance. A changed balance of
     * an existing card is an adjustment with a history entry that holds $comment, see update(). Give
     * $expectedBalance when the client saw a balance before it sent the new one.
     *
     * @throws Mage_Core_Exception_Input
     * @throws Mage_Core_Exception_Conflict when another card has the code, or the stored balance is not $expectedBalance
     */
    public function save(Maho_Giftcard_Model_Giftcard $giftcard, ?string $comment = null, ?float $expectedBalance = null): Maho_Giftcard_Model_Giftcard
    {
        $balance = (float) $giftcard->getData('balance');
        // A card that was not loaded has no stored balance to compare with
        $storedBalance = $giftcard->getOrigData('balance');
        if ($giftcard->getId() && $storedBalance !== null && $balance !== (float) $storedBalance) {
            // adjustBalance() is the only writer of the balance of an existing card
            $giftcard->setData('balance', (float) $storedBalance);
            return $this->update($giftcard, $balance, null, $comment, $expectedBalance);
        }
        $giftcard->save();
        return $giftcard;
    }

    /**
     * Give $giftcard the balance $newBalance and write a history entry with $comment. $expectedBalance is the
     * balance that the client saw. When $newBalance is that balance, the client did not change it, and the card
     * keeps its stored balance, also when a refund changed it since.
     *
     * @throws Mage_Core_Exception_Input when the model refuses the balance: below zero or above the highest balance
     * @throws Mage_Core_Exception_Conflict when the stored balance is not $expectedBalance
     */
    public function adjustBalance(Maho_Giftcard_Model_Giftcard $giftcard, float $newBalance, ?string $comment = null, ?float $expectedBalance = null): Maho_Giftcard_Model_Giftcard
    {
        if ($expectedBalance !== null && round($newBalance - $expectedBalance, 4) === 0.0) {
            return $giftcard;
        }
        // The model compares with the stored balance, which can differ from the loaded one
        $giftcard->adjustBalance($newBalance, $comment, $expectedBalance);
        return $giftcard;
    }

    /**
     * Save the balance, the status and the other changes of the existing card $giftcard together: when one
     * change fails, nothing is saved. An explicit status wins over the status that the new balance gives.
     *
     * @throws Mage_Core_Exception_Input
     * @throws Mage_Core_Exception_Conflict when another card has the code, or the stored balance is not $expectedBalance
     */
    public function update(Maho_Giftcard_Model_Giftcard $giftcard, ?float $balance, ?string $status, ?string $comment = null, ?float $expectedBalance = null): Maho_Giftcard_Model_Giftcard
    {
        $resource = $giftcard->getResource();
        $resource->beginTransaction();
        try {
            if ($balance !== null) {
                $this->adjustBalance($giftcard, $balance, $comment, $expectedBalance);
            }
            if ($status !== null && $status !== $giftcard->getStatus()) {
                $giftcard->setStatus($status);
            }
            $giftcard->save();
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
            // A card that an admin disabled before its date, for example after a refund, gets no email
            ->addFieldToFilter('main_table.status', Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
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
     * Return the balance of $giftcard to show in $store: in the current currency of the store when the card
     * converts to it, else in the currency of the card. A card with no website has no currency, so it gets its
     * stored balance with the current currency of the store.
     *
     * @return array{balance: float, currency: string}
     */
    public function getDisplayBalance(Maho_Giftcard_Model_Giftcard $giftcard, Mage_Core_Model_Store $store): array
    {
        $currencyCode = (string) $store->getCurrentCurrencyCode();
        if ($giftcard->getWebsiteIds() === []) {
            $balance = $giftcard->getBalance();
        } else {
            $balance = $giftcard->getBalanceIn($currencyCode);
            if ($balance === null) {
                $currencyCode = (string) $giftcard->getCurrencyCode();
                $balance = $giftcard->getBalance();
            }
        }

        return ['balance' => (float) $store->roundPrice($balance), 'currency' => $currencyCode];
    }

    /**
     * Apply the gift card $code to $quote. The errors of a code tell whether it exists, so a shopper has a limit
     * of codes per minute. Staff has no limit. $amount is the part of the balance to use, in the currency of the
     * cart, or null for the full balance. With $collectAndSave false, the caller collects the totals and saves the
     * cart, as the admin order page does.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the shopper is over the limit
     * @throws Mage_Core_Exception see validateForCart()
     */
    public function applyToCart(
        Mage_Sales_Model_Quote $quote,
        string $code,
        ?float $amount = null,
        Mage_Checkout_Model_Actor $actor = Mage_Checkout_Model_Actor::Customer,
        bool $collectAndSave = true,
    ): Mage_Sales_Model_Quote {
        if ($actor === Mage_Checkout_Model_Actor::Customer) {
            $limit = (int) Mage::getStoreConfig(self::XML_PATH_BALANCE_RATE_LIMIT);
            Mage::helper('core')
                ->rateLimiter('cart_giftcard', $limit, self::BALANCE_RATE_LIMIT_WINDOW, Maho\Security\RateLimitScope::Ip)
                ->attemptOrThrow();
        }
        $giftcard = $this->validateForCart($quote, trim($code));
        $code = (string) $giftcard->getCode();
        $appliedCodes = $this->getCartCodes($quote);

        // giftcard_codes holds base currency amounts: convert $amount with the rate that gave the prices of the cart
        $store = $quote->getStore();
        $baseCurrency = $store->getBaseCurrencyCode();
        if ($amount !== null && $store->getCurrentCurrencyCode() !== $baseCurrency) {
            $amount /= $store->getCurrentCurrencyRate();
        }
        $balance = (float) $giftcard->getBalance($baseCurrency);
        $appliedCodes[$code] = $amount === null ? $balance : min($amount, $balance);

        $quote->setGiftcardCodes(Mage::helper('core')->jsonEncode($appliedCodes));
        if ($collectAndSave) {
            Mage::getService('checkout/cart')->collectAndSave($quote);
        }

        return $quote;
    }

    /**
     * Return the gift card of $code when $quote can take it.
     *
     * @throws Mage_Core_Exception_Conflict when the card is already on the cart
     * @throws Mage_Core_Exception when the code is empty, the card does not exist or cannot pay for this cart
     */
    public function validateForCart(Mage_Sales_Model_Quote $quote, string $code): Maho_Giftcard_Model_Giftcard
    {
        $helper = Mage::helper('giftcard');
        if ($code === '') {
            throw new Mage_Core_Exception($helper->__('Please enter a gift card code.'));
        }

        foreach ($quote->getAllItems() as $item) {
            if ($item->getProductType() === 'giftcard') {
                throw new Mage_Core_Exception($helper->__('Gift cards cannot be used to purchase gift card products.'));
            }
        }

        // A card of another website gets the same message as an unknown code
        try {
            $giftcard = $this->getByCode($code, (int) $quote->getStore()->getWebsiteId());
        } catch (Mage_Core_Exception_NoSuchEntity) {
            throw new Mage_Core_Exception($helper->__('Gift card "%s" is not valid.', $code));
        }
        $code = (string) $giftcard->getCode();

        if (!$giftcard->isValid()) {
            throw new Mage_Core_Exception(match ($giftcard->getStatus()) {
                Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED => $helper->__('Gift card "%s" has expired.', $code),
                Maho_Giftcard_Model_Giftcard::STATUS_USED => $helper->__('Gift card "%s" has been fully used.', $code),
                default => $helper->__('Gift card "%s" is not active.', $code),
            });
        }

        if ($this->findCartCode($this->getCartCodes($quote), $code) !== null) {
            throw new Mage_Core_Exception_Conflict($helper->__('Gift card "%s" is already applied.', $code));
        }

        return $giftcard;
    }

    /**
     * Check each gift card of $quote against its balance in the database, before the order is placed. The cart
     * holds the amount from the time of applyToCart(), and the card can be spent elsewhere after that. An amount
     * above the balance becomes the balance, and the cart is saved.
     *
     * @throws Mage_Core_Exception when a card of the cart is not valid for the website or has no balance
     */
    public function revalidateCart(Mage_Sales_Model_Quote $quote): Mage_Sales_Model_Quote
    {
        $applied = $this->getCartCodes($quote);
        if ($applied === []) {
            return $quote;
        }

        $helper = Mage::helper('giftcard');
        $changed = false;
        $baseCurrency = $quote->getStore()->getBaseCurrencyCode();
        $websiteId = (int) $quote->getStore()->getWebsiteId();
        foreach ($applied as $code => $appliedAmount) {
            $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode((string) $code);
            if (!$giftcard->getId() || !$giftcard->isValidForWebsite($websiteId)) {
                throw new Mage_Core_Exception($helper->__('Gift card "%s" is no longer valid', $code));
            }
            $balance = (float) $giftcard->getBalance($baseCurrency);
            if ($balance <= 0) {
                throw new Mage_Core_Exception($helper->__('Gift card "%s" has no remaining balance', $code));
            }
            if ($balance < (float) $appliedAmount) {
                $applied[$code] = $balance;
                $changed = true;
            }
        }

        if ($changed) {
            $quote->setGiftcardCodes(Mage::helper('core')->jsonEncode($applied));
            Mage::getService('checkout/cart')->collectAndSave($quote);
        }

        return $quote;
    }

    /**
     * Remove the gift card $code from $quote. The case of the code does not matter. With $collectAndSave false,
     * the caller collects the totals and saves the cart, as the admin order page does.
     *
     * @throws Mage_Core_Exception_Input when the code is empty
     * @throws Mage_Core_Exception_NoSuchEntity when the card is not on the cart
     */
    public function removeFromCart(Mage_Sales_Model_Quote $quote, string $code, bool $collectAndSave = true): Mage_Sales_Model_Quote
    {
        $helper = Mage::helper('giftcard');
        if ($code === '') {
            throw new Mage_Core_Exception_Input()->addError('code', $helper->__('Gift card code is required'));
        }

        $appliedCodes = $this->getCartCodes($quote);
        $appliedCode = $this->findCartCode($appliedCodes, $code);
        if ($appliedCode === null) {
            throw new Mage_Core_Exception_NoSuchEntity($helper->__('Gift card "%s" is not applied to this cart', $code));
        }
        unset($appliedCodes[$appliedCode]);

        if ($appliedCodes === []) {
            $quote->setGiftcardCodes(null);
            $quote->setGiftcardAmount(0);
            $quote->setBaseGiftcardAmount(0);
        } else {
            $quote->setGiftcardCodes(Mage::helper('core')->jsonEncode($appliedCodes));
        }

        if ($collectAndSave) {
            Mage::getService('checkout/cart')->collectAndSave($quote);
        }

        return $quote;
    }

    /**
     * @return array<string, float|int|string> the code of each gift card of $quote => its amount in the base currency
     */
    private function getCartCodes(Mage_Sales_Model_Quote $quote): array
    {
        $codes = $quote->getGiftcardCodes();
        $codes = $codes ? Mage::helper('core')->jsonDecode($codes, true) : [];

        return is_array($codes) ? $codes : [];
    }

    /**
     * Return the key of $code in $appliedCodes. A cart from before the code was stored in its own case can hold
     * the code as the customer typed it, so the case does not matter.
     *
     * @param array<string, mixed> $appliedCodes
     */
    private function findCartCode(array $appliedCodes, string $code): ?string
    {
        $key = array_find_key($appliedCodes, fn(mixed $amount, int|string $appliedCode): bool => strcasecmp((string) $appliedCode, $code) === 0);

        return $key === null ? null : (string) $key;
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
