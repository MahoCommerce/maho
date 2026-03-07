<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Model_Observer
{
    /**
     * Create gift cards when invoice is paid
     *
     * Gift cards are only created after payment is confirmed to prevent
     * generating gift card codes for unpaid orders.
     *
     * @return void
     */
    public function createGiftcardsOnInvoicePaid(Maho\Event\Observer $observer)
    {
        /** @var Mage_Sales_Model_Order_Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();
        $order = $invoice->getOrder();

        // Only create when invoice is actually paid
        if ($invoice->getState() != Mage_Sales_Model_Order_Invoice::STATE_PAID) {
            return;
        }

        // Process each invoiced item
        foreach ($invoice->getAllItems() as $invoiceItem) {
            $orderItem = $invoiceItem->getOrderItem();

            // Check if item is a gift card
            if ($orderItem->getProductType() !== 'giftcard') {
                continue;
            }

            // Get gift card details from info_buyRequest
            $options = $orderItem->getProductOptions();
            $buyRequest = $options['info_buyRequest'] ?? [];

            $amount = $buyRequest['giftcard_amount'] ?? null;

            if ($amount === null || $amount <= 0) {
                continue; // Not a valid gift card
            }

            $recipientName = $buyRequest['giftcard_recipient_name'] ?? '';
            $recipientEmail = $buyRequest['giftcard_recipient_email'] ?? '';
            $senderName = $buyRequest['giftcard_sender_name'] ?? '';
            $senderEmail = $buyRequest['giftcard_sender_email'] ?? '';
            $message = $buyRequest['giftcard_message'] ?? '';

            // Generate gift cards for each invoiced quantity
            $qtyInvoiced = (int) $invoiceItem->getQty();
            for ($i = 0; $i < $qtyInvoiced; $i++) {
                $giftcard = $this->_createGiftcard(
                    (float) $amount,
                    $order,
                    $orderItem,
                    $recipientName,
                    $recipientEmail,
                    $senderName,
                    $senderEmail,
                    $message,
                );

                // Send gift card email to recipient
                $this->_sendGiftcardEmail($giftcard, $order);
            }
        }
    }

    /**
     * Create a gift card
     */
    protected function _createGiftcard(
        float $amount,
        Mage_Sales_Model_Order $order,
        Mage_Sales_Model_Order_Item $item,
        string $recipientName = '',
        string $recipientEmail = '',
        string $senderName = '',
        string $senderEmail = '',
        string $message = '',
    ): Maho_Giftcard_Model_Giftcard {
        $helper = Mage::helper('giftcard');

        // Get website from order
        $store = $order->getStore();
        $website = $store->getWebsite();

        // Convert amount to base currency using order's conversion rate
        // Gift card amount is in order currency, need to convert to base currency
        // base_to_order_rate converts base→order, so to go order→base we divide
        $baseToOrderRate = (float) $order->getBaseToOrderRate();
        $baseAmount = $baseToOrderRate > 0 ? $amount / $baseToOrderRate : $amount;

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setData([
            'code' => $helper->generateCode(),
            'status' => Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE,
            'website_id' => $website->getId(),
            'balance' => $baseAmount,
            'initial_balance' => $baseAmount,
            'recipient_name' => $recipientName,
            'recipient_email' => $recipientEmail,
            'sender_name' => $senderName,
            'sender_email' => $senderEmail,
            'message' => $message,
            'purchase_order_id' => $order->getId(),
            'purchase_order_item_id' => $item->getId(),
            'expires_at' => $helper->calculateExpirationDate(),
            'created_at' => Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT),
            'updated_at' => Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT),
        ]);

        $giftcard->save();

        // Add history entry
        $history = Mage::getModel('giftcard/history');
        $history->setData([
            'giftcard_id' => $giftcard->getId(),
            'action' => Maho_Giftcard_Model_Giftcard::ACTION_CREATED,
            'base_amount' => $baseAmount,
            'balance_before' => 0,
            'balance_after' => $baseAmount,
            'order_id' => $order->getId(),
            'comment' => "Created from order #{$order->getIncrementId()}",
            'created_at' => Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT),
        ]);
        $history->save();

        return $giftcard;
    }

    /**
     * Send gift card email to recipient
     *
     * @param Maho_Giftcard_Model_Giftcard $giftcard
     * @param Mage_Sales_Model_Order $order
     * @return void
     */
    protected function _sendGiftcardEmail($giftcard, $order)
    {
        // Only send if recipient email is provided
        if (!$giftcard->getRecipientEmail()) {
            return;
        }

        try {
            $helper = Mage::helper('giftcard');

            // Prepare email variables
            $storeCurrencyCode = $order->getStore()->getCurrentCurrencyCode();
            $emailVars = [
                'giftcard' => $giftcard,
                'order' => $order,
                'code' => $giftcard->getCode(),
                'balance' => Mage::app()->getLocale()->formatCurrency($giftcard->getBalance($storeCurrencyCode), $storeCurrencyCode),
                'recipient_name' => $giftcard->getRecipientName(),
                'sender_name' => $giftcard->getSenderName(),
                'message' => $giftcard->getMessage(),
                'qr_code_url' => $helper->getQrCodeUrl($giftcard->getCode()),
                'store' => Mage::app()->getStore(),
            ];

            // Send email
            $emailTemplate = Mage::getModel('core/email_template');
            $emailTemplate->setDesignConfig(['area' => 'frontend', 'store' => $order->getStoreId()])
                ->sendTransactional(
                    'giftcard_email_template',
                    Mage::getStoreConfig('sales/email/identity', $order->getStoreId()),
                    $giftcard->getRecipientEmail(),
                    $giftcard->getRecipientName(),
                    $emailVars,
                    $order->getStoreId(),
                );

            // Email sent successfully
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Save gift card product attributes
     *
     * @return void
     */
    public function catalogProductSaveBefore(Maho\Event\Observer $observer)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = $observer->getEvent()->getProduct();

        // Only process gift card products
        if ($product->getTypeId() !== 'giftcard') {
            return;
        }

        // Get the product data from request
        $data = $product->getData();

        // Save gift card specific attributes
        $attributes = [
            'giftcard_type',
            'giftcard_amounts',
            'giftcard_min_amount',
            'giftcard_max_amount',
            'giftcard_allow_message',
            'giftcard_lifetime',
        ];

        foreach ($attributes as $attribute) {
            if (isset($data[$attribute])) {
                $product->setData($attribute, $data[$attribute]);
            }
        }
    }

    /**
     * Set custom price on quote item for gift cards
     *
     * @return void
     */
    public function setGiftcardPrice(Maho\Event\Observer $observer)
    {
        /** @var Mage_Sales_Model_Quote_Item $quoteItem */
        $quoteItem = $observer->getEvent()->getQuoteItem();

        // Only process gift card products
        if ($quoteItem->getProductType() !== 'giftcard') {
            return;
        }

        // Check if cart already has giftcard codes applied
        $quote = $quoteItem->getQuote();
        $giftcardCodes = $quote->getGiftcardCodes();
        if ($giftcardCodes !== null && $giftcardCodes !== '') {
            $codes = json_decode($giftcardCodes, true);
            if (is_array($codes) && $codes !== []) {
                throw new Mage_Core_Exception(
                    Mage::helper('giftcard')->__('Gift card products cannot be added when a gift card code is applied. Please remove the gift card code first.'),
                );
            }
        }

        // Get the gift card amount from buy request
        $buyRequest = $quoteItem->getBuyRequest();
        if ($buyRequest && $buyRequest->getGiftcardAmount()) {
            $amount = (float) $buyRequest->getGiftcardAmount();
            $quoteItem->setCustomPrice($amount);
            $quoteItem->setOriginalCustomPrice($amount);
            $quoteItem->getProduct()->setIsSuperMode(true);

            // Ensure additional_options are added to the quote item
            $additionalOptions = Mage::helper('giftcard')->buildAdditionalOptions($buyRequest);

            if ($additionalOptions !== []) {
                $quoteItem->addOption([
                    'code' => 'additional_options',
                    'value' => serialize($additionalOptions),
                ]);
            }
        }
    }

    /**
     * Apply gift card amounts to order during quote address-to-order conversion
     *
     * This event fires AFTER fieldsets are copied from address to order,
     * so we can reduce the grand_total that was copied from address.
     *
     * @return void
     */
    public function applyGiftcardToOrder(Maho\Event\Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        /** @var Mage_Sales_Model_Quote_Address $address */
        $address = $observer->getEvent()->getAddress();

        if (!$address) {
            return;
        }

        $quote = $address->getQuote();

        // Get gift card amount - try address first, then quote
        $baseGiftcardAmount = abs((float) $address->getBaseGiftcardAmount());
        $giftcardAmount = abs((float) $address->getGiftcardAmount());

        if (!$baseGiftcardAmount && $quote) {
            $baseGiftcardAmount = abs((float) $quote->getBaseGiftcardAmount());
            $giftcardAmount = abs((float) $quote->getGiftcardAmount());
        }

        // Get codes from quote
        $giftcardCodes = $quote ? $quote->getGiftcardCodes() : null;

        if ($baseGiftcardAmount > 0) {
            // Set gift card amounts on order
            // Grand total is already reduced during quote total collection
            $order->setBaseGiftcardAmount($baseGiftcardAmount);
            $order->setGiftcardAmount($giftcardAmount);
            if ($giftcardCodes) {
                $order->setGiftcardCodes($giftcardCodes);
            }
        }
    }

    /**
     * Deduct gift card balance when order is placed
     *
     * @return void
     */
    public function deductGiftcardBalance(Maho\Event\Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();

        // Get gift card info from quote
        $quote = $order->getQuote();
        if (!$quote) {
            $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
        }

        $giftcardCodes = $quote->getGiftcardCodes();
        if (!$giftcardCodes) {
            return;
        }

        $codes = json_decode($giftcardCodes, true);
        if (!is_array($codes) || $codes === []) {
            return;
        }

        // Get the gift card amount from the order
        $giftcardAmount = 0;
        $baseGiftcardAmount = 0;

        // Try to get from the order addresses first
        foreach ($order->getAddressesCollection() as $address) {
            if ($address->getAddressType() == 'shipping' || ($address->getAddressType() == 'billing' && $order->getIsVirtual())) {
                $giftcardAmount = abs((float) $address->getGiftcardAmount());
                $baseGiftcardAmount = abs((float) $address->getBaseGiftcardAmount());
                break;
            }
        }

        // If not found on addresses, try to get from quote directly
        if (!$baseGiftcardAmount && $quote) {
            $baseGiftcardAmount = abs((float) $quote->getBaseGiftcardAmount());
            $giftcardAmount = abs((float) $quote->getGiftcardAmount());
        }

        // If still not found, calculate from codes stored in quote
        if (!$baseGiftcardAmount && $quote) {
            $quoteCodesJson = $quote->getGiftcardCodes();
            if ($quoteCodesJson) {
                $quoteCodes = json_decode($quoteCodesJson, true);
                if (is_array($quoteCodes)) {
                    foreach ($quoteCodes as $amount) {
                        $baseGiftcardAmount += (float) $amount;
                    }
                    $giftcardAmount = $order->getStore()->convertPrice($baseGiftcardAmount, false);
                }
            }
        }

        // Set gift card amounts on order (codes/amounts may already be set
        // by fieldset conversion + applyGiftcardToOrder, but ensure they're present)
        $order->setGiftcardCodes($giftcardCodes);
        $order->setBaseGiftcardAmount($baseGiftcardAmount);
        $order->setGiftcardAmount($giftcardAmount);

        // Grand total is NOT modified here — it was already reduced during
        // quote total collection via Total_Quote::collect() → _addAmount(-$amount)
        // and carried over to the order during quote-to-order conversion.

        // Add gift card info to payment additional information for display in grid
        $payment = $order->getPayment();
        if ($payment && $baseGiftcardAmount > 0) {
            $additionalInfo = $payment->getAdditionalInformation();
            if (!is_array($additionalInfo)) {
                $additionalInfo = [];
            }

            // Format codes for display
            $displayCodes = [];
            foreach (array_keys($codes) as $code) {
                $displayCodes[] = Mage::helper('giftcard')->maskCode($code);
            }

            $additionalInfo['gift_cards_used'] = implode(', ', $displayCodes);
            $additionalInfo['gift_cards_amount'] = $order->getStore()->formatPrice($giftcardAmount);
            $payment->setAdditionalInformation($additionalInfo);
            $payment->save();
        }

        $order->save();

        // Deduct balance from each gift card with row locking
        // Don't start a new transaction - use the order's transaction so rollback works
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');

        $orderBaseCurrency = $order->getBaseCurrencyCode();

        // Calculate proportional amounts if codes have full balances but order has capped amount
        $totalFromCodes = array_sum($codes);
        $actualAmounts = [];
        if ($totalFromCodes > 0 && $baseGiftcardAmount > 0) {
            $remainingAmount = $baseGiftcardAmount;
            $cardCodes = array_keys($codes);
            foreach ($cardCodes as $i => $code) {
                if ($i === count($cardCodes) - 1) {
                    // Last card gets remaining amount to avoid rounding issues
                    $actualAmounts[$code] = $remainingAmount;
                } else {
                    // Proportional distribution
                    $proportion = $codes[$code] / $totalFromCodes;
                    $amount = min($codes[$code], round($baseGiftcardAmount * $proportion, 2));
                    $actualAmounts[$code] = $amount;
                    $remainingAmount -= $amount;
                }
            }
        } else {
            $actualAmounts = $codes;
        }

        foreach ($actualAmounts as $code => $usedAmount) {
            // Load gift card with row lock to prevent race conditions
            $giftcardTable = Mage::getSingleton('core/resource')->getTableName('giftcard/giftcard');
            $select = $adapter->select()
                ->from($giftcardTable)
                ->where('code = ?', $code)
                ->forUpdate();

            $giftcardData = $adapter->fetchRow($select);

            if (!$giftcardData || !isset($giftcardData['giftcard_id'])) {
                continue;
            }

            $baseBalanceBefore = (float) $giftcardData['balance'];

            // Cap deduction to available balance (don't deduct more than what's available)
            $amountToDeduct = min($usedAmount, $baseBalanceBefore);
            if ($amountToDeduct <= 0) {
                continue;
            }

            $newBalance = $baseBalanceBefore - $amountToDeduct;

            // Prepare update data
            $updateData = [
                'balance' => $newBalance,
                'updated_at' => Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT),
            ];

            // Update status if fully used
            if ($newBalance <= 0) {
                $updateData['status'] = Maho_Giftcard_Model_Giftcard::STATUS_USED;
            }

            // Update gift card balance directly with locked row
            $adapter->update(
                $giftcardTable,
                $updateData,
                ['giftcard_id = ?' => $giftcardData['giftcard_id']],
            );

            // Log the usage in history
            $historyTable = Mage::getSingleton('core/resource')->getTableName('giftcard/history');
            $adapter->insert($historyTable, [
                'giftcard_id' => $giftcardData['giftcard_id'],
                'action' => Maho_Giftcard_Model_Giftcard::ACTION_USED,
                'base_amount' => -$amountToDeduct,
                'balance_before' => $baseBalanceBefore,
                'balance_after' => $newBalance,
                'order_id' => $order->getId(),
                'comment' => "Used in order #{$order->getIncrementId()}",
                'created_at' => Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT),
            ]);
        }
    }

    /**
     * Refund gift card balance when credit memo is created
     *
     * @return void
     */
    public function refundGiftcardBalance(Maho\Event\Observer $observer)
    {
        /** @var Mage_Sales_Model_Order_Creditmemo $creditmemo */
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $order = $creditmemo->getOrder();

        // Get the gift card amount being refunded from this credit memo
        $baseGiftcardAmount = abs((float) $creditmemo->getBaseGiftcardAmount());

        if ($baseGiftcardAmount <= 0) {
            return;
        }

        // Get the codes from the order
        $giftcardCodes = $order->getGiftcardCodes();
        if (!$giftcardCodes) {
            return;
        }

        $codes = json_decode($giftcardCodes, true);
        if (!is_array($codes) || $codes === []) {
            return;
        }

        // Calculate total that was applied from these codes
        $totalApplied = array_sum($codes);
        if ($totalApplied <= 0) {
            return;
        }

        // Refund proportionally to each gift card
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $giftcardTable = Mage::getSingleton('core/resource')->getTableName('giftcard/giftcard');
        $historyTable = Mage::getSingleton('core/resource')->getTableName('giftcard/history');

        foreach ($codes as $code => $appliedAmount) {
            if ($appliedAmount <= 0) {
                continue;
            }

            // Calculate proportional refund amount
            $refundAmount = ($appliedAmount / $totalApplied) * $baseGiftcardAmount;
            if ($refundAmount <= 0) {
                continue;
            }

            // Load gift card with row lock to prevent race conditions
            $select = $adapter->select()
                ->from($giftcardTable)
                ->where('code = ?', $code)
                ->forUpdate();

            $giftcardData = $adapter->fetchRow($select);

            if (!$giftcardData || !isset($giftcardData['giftcard_id'])) {
                continue;
            }

            $balanceBefore = (float) $giftcardData['balance'];
            $newBalance = $balanceBefore + $refundAmount;
            $currentStatus = $giftcardData['status'];
            $currentExpiresAt = $giftcardData['expires_at'];

            // Determine new status and expiration
            $newStatus = Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE;
            $newExpiresAt = $currentExpiresAt;
            $historyComment = "Refund for order #{$order->getIncrementId()}";

            // Check if card is expired or needs extension
            $isExpired = $currentStatus === Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED;
            $extensionDays = (int) Mage::getStoreConfig('giftcard/general/refund_expiration_extension', $order->getStoreId());
            $needsExtension = false;

            if ($currentExpiresAt && $extensionDays > 0) {
                $now = Mage::app()->getLocale()->utcDate(null, null, true);
                $expiresAt = new DateTime($currentExpiresAt, new DateTimeZone('UTC'));

                // Calculate the minimum acceptable expiration (now + extension days)
                $minimumExpiration = (clone $now)->modify("+{$extensionDays} days");

                // Extend if card is expired OR will expire before the minimum
                $needsExtension = $expiresAt < $minimumExpiration;
            } elseif ($isExpired && $extensionDays > 0) {
                // Card is expired but has no expiration date set (edge case)
                $needsExtension = true;
            }

            // Extend expiration if needed
            if ($needsExtension && $extensionDays > 0) {
                $newExpiration = Mage::app()->getLocale()->utcDate(null, null, true);
                $newExpiration->modify("+{$extensionDays} days");
                $newExpiresAt = $newExpiration->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                $historyComment .= " (expiration extended to {$extensionDays} days from now)";
            } elseif ($isExpired && $extensionDays === 0) {
                // If extension is 0 and card is expired, keep it expired but still add balance
                $newStatus = Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED;
            }

            // Build update data
            $updateData = [
                'balance' => $newBalance,
                'status' => $newStatus,
                'updated_at' => Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT),
            ];

            // Only update expires_at if we're extending it
            if ($newExpiresAt !== $currentExpiresAt) {
                $updateData['expires_at'] = $newExpiresAt;
            }

            // Update gift card balance
            $adapter->update(
                $giftcardTable,
                $updateData,
                ['giftcard_id = ?' => $giftcardData['giftcard_id']],
            );

            // Log the refund in history
            $adapter->insert($historyTable, [
                'giftcard_id' => $giftcardData['giftcard_id'],
                'action' => Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED,
                'base_amount' => $refundAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $newBalance,
                'order_id' => $order->getId(),
                'comment' => $historyComment,
                'created_at' => Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT),
            ]);
        }
    }

    /**
     * Refund gift card balance when order is canceled
     *
     * When an order is canceled without creating a credit memo, we need to
     * restore the full gift card balance that was used in the order.
     *
     * @return void
     */
    public function refundGiftcardOnOrderCancel(Maho\Event\Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();

        // Get the base gift card amount that was applied to this order
        $baseGiftcardAmount = abs((float) $order->getBaseGiftcardAmount());

        if ($baseGiftcardAmount <= 0) {
            return;
        }

        // Get the codes from the order
        $giftcardCodes = $order->getGiftcardCodes();
        if (!$giftcardCodes) {
            return;
        }

        $codes = json_decode($giftcardCodes, true);
        if (!is_array($codes) || $codes === []) {
            return;
        }

        // Refund the full amount to each gift card
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $giftcardTable = Mage::getSingleton('core/resource')->getTableName('giftcard/giftcard');
        $historyTable = Mage::getSingleton('core/resource')->getTableName('giftcard/history');

        foreach ($codes as $code => $appliedAmount) {
            if ($appliedAmount <= 0) {
                continue;
            }

            // Load gift card with row lock to prevent race conditions
            $select = $adapter->select()
                ->from($giftcardTable)
                ->where('code = ?', $code)
                ->forUpdate();

            $giftcardData = $adapter->fetchRow($select);

            if (!$giftcardData || !isset($giftcardData['giftcard_id'])) {
                continue;
            }

            $balanceBefore = (float) $giftcardData['balance'];
            $newBalance = $balanceBefore + $appliedAmount;
            $currentStatus = $giftcardData['status'];
            $currentExpiresAt = $giftcardData['expires_at'];

            // Determine new status and expiration
            $newStatus = Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE;
            $newExpiresAt = $currentExpiresAt;
            $historyComment = "Refund for canceled order #{$order->getIncrementId()}";

            // Check if card is expired or needs extension
            $isExpired = $currentStatus === Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED;
            $extensionDays = (int) Mage::getStoreConfig('giftcard/general/refund_expiration_extension', $order->getStoreId());
            $needsExtension = false;

            if ($currentExpiresAt && $extensionDays > 0) {
                $now = Mage::app()->getLocale()->utcDate(null, null, true);
                $expiresAt = new DateTime($currentExpiresAt, new DateTimeZone('UTC'));

                // Calculate the minimum acceptable expiration (now + extension days)
                $minimumExpiration = (clone $now)->modify("+{$extensionDays} days");

                // Extend if card is expired OR will expire before the minimum
                $needsExtension = $expiresAt < $minimumExpiration;
            } elseif ($isExpired && $extensionDays > 0) {
                // Card is expired but has no expiration date set (edge case)
                $needsExtension = true;
            }

            // Extend expiration if needed
            if ($needsExtension && $extensionDays > 0) {
                $newExpiration = Mage::app()->getLocale()->utcDate(null, null, true);
                $newExpiration->modify("+{$extensionDays} days");
                $newExpiresAt = $newExpiration->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                $historyComment .= " (expiration extended to {$extensionDays} days from now)";
            } elseif ($isExpired && $extensionDays === 0) {
                // If extension is 0 and card is expired, keep it expired but still add balance
                $newStatus = Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED;
            }

            // Build update data
            $updateData = [
                'balance' => $newBalance,
                'status' => $newStatus,
                'updated_at' => Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT),
            ];

            // Only update expires_at if we're extending it
            if ($newExpiresAt !== $currentExpiresAt) {
                $updateData['expires_at'] = $newExpiresAt;
            }

            // Update gift card balance
            $adapter->update(
                $giftcardTable,
                $updateData,
                ['giftcard_id = ?' => $giftcardData['giftcard_id']],
            );

            // Log the refund in history
            $adapter->insert($historyTable, [
                'giftcard_id' => $giftcardData['giftcard_id'],
                'action' => Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED,
                'base_amount' => $appliedAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $newBalance,
                'order_id' => $order->getId(),
                'comment' => $historyComment,
                'created_at' => Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT),
            ]);
        }
    }

    /**
     * Process gift card in admin order create
     */
    public function processAdminOrderGiftcard(Maho\Event\Observer $observer): void
    {
        /** @var Mage_Core_Controller_Request_Http $requestModel */
        $requestModel = $observer->getEvent()->getRequestModel();
        $orderCreateModel = $observer->getEvent()->getOrderCreateModel();

        if (!$requestModel) {
            return;
        }

        // Check for gift card data in request
        $orderData = $requestModel->getPost('order') ?? [];
        $giftcardData = $orderData['giftcard'] ?? [];

        if (empty($giftcardData['code'])) {
            return;
        }

        $code = trim((string) $giftcardData['code']);
        $action = $giftcardData['action'] ?? 'apply';
        $quote = $orderCreateModel->getQuote();
        $session = Mage::getSingleton('adminhtml/session_quote');

        // Get currently applied codes
        $appliedCodes = $quote->getGiftcardCodes();
        if ($appliedCodes) {
            $appliedCodes = json_decode($appliedCodes, true);
        }
        if (!is_array($appliedCodes)) {
            $appliedCodes = [];
        }

        if ($action === 'remove') {
            // Remove gift card
            if (isset($appliedCodes[$code])) {
                unset($appliedCodes[$code]);

                if ($appliedCodes === []) {
                    $quote->setGiftcardCodes(null);
                    $quote->setGiftcardAmount(0);
                    $quote->setBaseGiftcardAmount(0);
                } else {
                    $quote->setGiftcardCodes(json_encode($appliedCodes));
                }

                $session->addSuccess(
                    Mage::helper('giftcard')->__('Gift card was removed.'),
                );
            }
        } else {
            // Apply gift card
            try {
                // Check if cart has gift card products
                foreach ($quote->getAllItems() as $item) {
                    if ($item->getProductType() === 'giftcard') {
                        throw new Mage_Core_Exception(
                            Mage::helper('giftcard')->__('Gift cards cannot be used to purchase gift card products.'),
                        );
                    }
                }

                // Load gift card by code
                $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode($code);

                if (!$giftcard->getId()) {
                    throw new Mage_Core_Exception(
                        Mage::helper('giftcard')->__('Gift card "%s" is not valid.', $code),
                    );
                }

                // Check website validity
                $websiteId = (int) $quote->getStore()->getWebsiteId();
                if ((int) $giftcard->getWebsiteId() !== $websiteId) {
                    throw new Mage_Core_Exception(
                        Mage::helper('giftcard')->__('Gift card "%s" is not valid for this website.', $code),
                    );
                }

                if (!$giftcard->isValid()) {
                    if ($giftcard->getStatus() === Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED) {
                        throw new Mage_Core_Exception(
                            Mage::helper('giftcard')->__('Gift card "%s" has expired.', $code),
                        );
                    }
                    if ($giftcard->getStatus() === Maho_Giftcard_Model_Giftcard::STATUS_USED) {
                        throw new Mage_Core_Exception(
                            Mage::helper('giftcard')->__('Gift card "%s" has been fully used.', $code),
                        );
                    }
                    throw new Mage_Core_Exception(
                        Mage::helper('giftcard')->__('Gift card "%s" is not active.', $code),
                    );
                }

                // Check if already applied
                if (isset($appliedCodes[$code])) {
                    throw new Mage_Core_Exception(
                        Mage::helper('giftcard')->__('Gift card "%s" is already applied.', $code),
                    );
                }

                // Store gift card code with placeholder amount (collect will calculate actual amount)
                $appliedCodes[$code] = 0;

                $quote->setGiftcardCodes(json_encode($appliedCodes));

                $session->addSuccess(
                    Mage::helper('giftcard')->__('Gift card "%s" was applied.', $code),
                );

            } catch (Mage_Core_Exception $e) {
                $session->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::logException($e);
                $session->addError(
                    Mage::helper('giftcard')->__('Cannot apply gift card.'),
                );
            }
        }

        // Mark for recollection
        $orderCreateModel->setRecollect(true);
    }

    /**
     * Filter payment methods for zero-total orders covered by gift cards
     * Prevents non-zero-total payment methods (like Check/Money Order) from showing
     * when gift cards fully cover the order. Only the "free" payment method should show.
     */
    public function filterPaymentMethodsForZeroTotal(Maho\Event\Observer $observer): void
    {
        /** @var stdClass $result */
        $result = $observer->getResult();
        /** @var Mage_Payment_Model_Method_Abstract $methodInstance */
        $methodInstance = $observer->getMethodInstance();
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $observer->getQuote();

        if (!$quote) {
            return;
        }

        // Check if gift cards fully cover the order
        $giftcardAmount = abs((float) $quote->getGiftcardAmount());
        $grandTotal = (float) $quote->getGrandTotal();
        $isFullyCovered = ($giftcardAmount > 0 && $grandTotal <= 0.01);

        if (!$isFullyCovered) {
            return;
        }

        // When gift cards fully cover the order (grand total = $0),
        // only allow the "free" payment method. Disable all others.
        $methodCode = $methodInstance->getCode();
        if ($methodCode === 'free') {
            // Force-enable the free payment method when gift cards fully cover the order
            $result->isAvailable = true;
        } else {
            // Disable all other payment methods
            $result->isAvailable = false;
        }
    }
}
