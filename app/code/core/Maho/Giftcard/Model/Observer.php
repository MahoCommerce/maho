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
     * Generate gift cards after order is placed
     *
     * @return void
     */
    public function generateGiftcards(Maho\Event\Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();

        foreach ($order->getAllItems() as $item) {
            // Check if item is a gift card
            if ($item->getProductType() !== 'giftcard') {
                continue;
            }

            // Get gift card details from info_buyRequest
            $options = $item->getProductOptions();
            $buyRequest = $options['info_buyRequest'] ?? [];

            $amount = $buyRequest['giftcard_amount'] ?? null;

            if (!$amount || $amount <= 0) {
                continue; // Not a valid gift card
            }

            $recipientName = $buyRequest['giftcard_recipient_name'] ?? '';
            $recipientEmail = $buyRequest['giftcard_recipient_email'] ?? '';
            $senderName = $buyRequest['giftcard_sender_name'] ?? '';
            $senderEmail = $buyRequest['giftcard_sender_email'] ?? '';
            $message = $buyRequest['giftcard_message'] ?? '';

            // Generate gift cards for each quantity
            for ($i = 0; $i < $item->getQtyOrdered(); $i++) {
                $this->_createGiftcard(
                    (float) $amount,
                    $order,
                    $item,
                    $recipientName,
                    $recipientEmail,
                    $senderName,
                    $senderEmail,
                    $message,
                );
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
    ): void {
        $helper = Mage::helper('giftcard');

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setData([
            'code' => $helper->generateCode(),
            'status' => Maho_Giftcard_Model_Giftcard::STATUS_PENDING,
            'balance' => $amount,
            'initial_balance' => $amount,
            'currency_code' => $order->getOrderCurrencyCode(),
            'recipient_name' => $recipientName,
            'recipient_email' => $recipientEmail,
            'sender_name' => $senderName,
            'sender_email' => $senderEmail,
            'message' => $message,
            'purchase_order_id' => $order->getId(),
            'purchase_order_item_id' => $item->getId(),
            'expires_at' => $helper->calculateExpirationDate(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $giftcard->save();

        // Add history entry
        $history = Mage::getModel('giftcard/history');
        $history->setData([
            'giftcard_id' => $giftcard->getId(),
            'action' => Maho_Giftcard_Model_Giftcard::ACTION_CREATED,
            'amount' => $amount,
            'balance_before' => 0,
            'balance_after' => $amount,
            'order_id' => $order->getId(),
            'comment' => "Created from order #{$order->getIncrementId()}",
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $history->save();

        // Gift card created successfully
    }

    /**
     * Activate gift cards when invoice is paid
     *
     * @return void
     */
    public function activateGiftcardsOnInvoicePaid(Maho\Event\Observer $observer)
    {
        /** @var Mage_Sales_Model_Order_Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();
        $order = $invoice->getOrder();

        // Only activate when invoice is actually paid
        if ($invoice->getState() != Mage_Sales_Model_Order_Invoice::STATE_PAID) {
            return;
        }

        // Find all pending gift cards for this order
        $giftcards = Mage::getModel('giftcard/giftcard')->getCollection()
            ->addFieldToFilter('purchase_order_id', $order->getId())
            ->addFieldToFilter('status', Maho_Giftcard_Model_Giftcard::STATUS_PENDING);

        foreach ($giftcards as $giftcard) {
            // Activate the gift card
            $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
            $giftcard->save();

            // Add history entry
            $history = Mage::getModel('giftcard/history');
            $history->setData([
                'giftcard_id' => $giftcard->getId(),
                'action' => 'activated',
                'amount' => 0,
                'balance_before' => $giftcard->getBalance(),
                'balance_after' => $giftcard->getBalance(),
                'order_id' => $order->getId(),
                'comment' => "Activated after invoice payment for order #{$order->getIncrementId()}",
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $history->save();

            // Send gift card email to recipient
            $this->_sendGiftcardEmail($giftcard, $order);

            // Gift card activated successfully
        }
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
        try {
            $helper = Mage::helper('giftcard');

            // Prepare email variables
            $emailVars = [
                'giftcard' => $giftcard,
                'order' => $order,
                'code' => $giftcard->getCode(),
                'balance' => Mage::helper('core')->currency($giftcard->getBalance(), true, false),
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
            'giftcard_is_redeemable',
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

        // Get the gift card amount from buy request
        $buyRequest = $quoteItem->getBuyRequest();
        if ($buyRequest && $buyRequest->getGiftcardAmount()) {
            $amount = (float) $buyRequest->getGiftcardAmount();
            $quoteItem->setCustomPrice($amount);
            $quoteItem->setOriginalCustomPrice($amount);
            $quoteItem->getProduct()->setIsSuperMode(true);

            // Ensure additional_options are added to the quote item
            $additionalOptions = [];

            if ($buyRequest->getGiftcardRecipientName()) {
                $additionalOptions[] = [
                    'label' => 'Recipient Name',
                    'value' => $buyRequest->getGiftcardRecipientName(),
                ];
            }

            if ($buyRequest->getGiftcardRecipientEmail()) {
                $additionalOptions[] = [
                    'label' => 'Recipient Email',
                    'value' => $buyRequest->getGiftcardRecipientEmail(),
                ];
            }

            if ($buyRequest->getGiftcardMessage()) {
                $additionalOptions[] = [
                    'label' => 'Message',
                    'value' => $buyRequest->getGiftcardMessage(),
                ];
            }

            // Add additional options to quote item
            $quoteItem->addOption([
                'code' => 'additional_options',
                'value' => serialize($additionalOptions),
            ]);
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
        if (!is_array($codes) || empty($codes)) {
            return;
        }

        // Get the gift card amount from the order
        $giftcardAmount = 0;
        $baseGiftcardAmount = 0;

        // Try to get from the shipping address first
        foreach ($order->getAllAddresses() as $address) {
            if ($address->getAddressType() == 'shipping' || ($address->getAddressType() == 'billing' && $order->getIsVirtual())) {
                $giftcardAmount = abs($address->getGiftcardAmount());
                $baseGiftcardAmount = abs($address->getBaseGiftcardAmount());
                break;
            }
        }

        // If not found on addresses, try to calculate from codes
        if (!$baseGiftcardAmount) {
            foreach ($codes as $code => $amount) {
                $baseGiftcardAmount += (float) $amount;
            }
            $giftcardAmount = $order->getStore()->convertPrice($baseGiftcardAmount, false);
        }

        // Save gift card info to order
        $order->setGiftcardCodes($giftcardCodes);
        $order->setBaseGiftcardAmount($baseGiftcardAmount);
        $order->setGiftcardAmount($giftcardAmount);

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
                // Show partial code for security
                if (strlen($code) > 10) {
                    $displayCodes[] = substr($code, 0, 5) . '...' . substr($code, -4);
                } else {
                    $displayCodes[] = $code;
                }
            }

            $additionalInfo['gift_cards_used'] = implode(', ', $displayCodes);
            $additionalInfo['gift_cards_amount'] = $order->getStore()->formatPrice($giftcardAmount);
            $payment->setAdditionalInformation($additionalInfo);
            $payment->save();
        }

        $order->save();

        // Deduct balance from each gift card
        foreach ($codes as $code => $usedAmount) {
            $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode($code);
            if (!$giftcard->getId()) {
                continue;
            }

            $balanceBefore = $giftcard->getBalance();
            $newBalance = max(0, $balanceBefore - (float) $usedAmount);

            // Update gift card balance
            $giftcard->setBalance($newBalance);
            $giftcard->save();

            // Log the usage in history
            $history = Mage::getModel('giftcard/history');
            $history->setData([
                'giftcard_id' => $giftcard->getId(),
                'action' => Maho_Giftcard_Model_Giftcard::ACTION_USED,
                'amount' => -(float) $usedAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $newBalance,
                'order_id' => $order->getId(),
                'comment' => "Used in order #{$order->getIncrementId()}",
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $history->save();

            // Gift card balance updated
        }
    }

    /**
     * Add gift card total to admin order view
     *
     * @return void
     */
    public function addGiftcardTotalToAdminOrder(Maho\Event\Observer $observer)
    {
        $block = $observer->getEvent()->getBlock();

        // Check if this is an order totals block
        if ($block->getNameInLayout() != 'order_totals') {
            return;
        }

        $order = $block->getOrder();
        if (!$order || !$order->getId()) {
            return;
        }

        $giftcardAmount = $order->getGiftcardAmount();

        if ($giftcardAmount != 0) {
            // Get gift card codes for display
            $codes = [];
            $giftcardCodes = $order->getGiftcardCodes();
            if ($giftcardCodes) {
                $codesArray = json_decode($giftcardCodes, true);
                if (is_array($codesArray)) {
                    // Show partial codes for security
                    foreach (array_keys($codesArray) as $code) {
                        if (strlen($code) > 10) {
                            $codes[] = substr($code, 0, 5) . '...' . substr($code, -4);
                        } else {
                            $codes[] = $code;
                        }
                    }
                }
            }

            $label = Mage::helper('giftcard')->__('Gift Cards');
            if (!empty($codes)) {
                $label .= ' (' . implode(', ', $codes) . ')';
            }

            $total = new Maho\DataObject([
                'code'       => 'giftcard',
                'value'      => -abs((float) $giftcardAmount),
                'base_value' => -abs((float) $order->getBaseGiftcardAmount()),
                'label'      => $label,
                'strong'     => false,
            ]);

            $block->addTotalBefore($total, 'grand_total');
        }
    }

    /**
     * Update payment method label when fully paid by gift card
     *
     * @return void
     */
    public function updatePaymentMethodForGiftcard(Maho\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order) {
            $order = $observer->getEvent()->getInvoice()->getOrder();
        }
        if (!$order) {
            return;
        }

        $giftcardAmount = abs($order->getGiftcardAmount());
        $grandTotal = $order->getGrandTotal();

        // If order is fully paid by gift card
        if ($giftcardAmount > 0 && $giftcardAmount >= $grandTotal) {
            $payment = $order->getPayment();
            if ($payment) {
                // Store original payment method
                $payment->setAdditionalInformation('original_method', $payment->getMethod());
                // Update display to show gift card
                $payment->setMethod('giftcard');
                $payment->setMethodTitle('Gift Card');
            }
        }
    }

    /**
     * Auto-select gift card payment method when order is fully covered
     *
     * @return void
     */
    public function autoSelectPaymentMethod(Maho\Event\Observer $observer)
    {
        $controller = $observer->getEvent()->getControllerAction();
        $request = $controller->getRequest();
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        // Check if gift cards fully cover the order
        $giftcardAmount = abs((float) $quote->getGiftcardAmount());
        $grandTotal = (float) $quote->getGrandTotal();

        if ($giftcardAmount > 0 && $grandTotal <= 0.01) {
            // Auto-select gift card payment method
            $payment = $request->getPost('payment', []);

            // Only override if no payment method was selected
            if (empty($payment['method'])) {
                $payment['method'] = 'giftcard';
                $request->setPost('payment', $payment);

                // Also set it directly on the quote
                try {
                    $quote->getPayment()->setMethod('giftcard');
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }
        }
    }
}
