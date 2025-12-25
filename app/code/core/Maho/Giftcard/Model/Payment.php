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

/**
 * Gift Card Payment Method
 */
class Maho_Giftcard_Model_Payment extends Mage_Payment_Model_Method_Abstract
{
    /** @var string */
    protected $_code = 'giftcard';
    /** @var string */
    protected $_formBlockType = 'giftcard/payment_form';
    /** @var string */
    protected $_infoBlockType = 'giftcard/payment_info';
    /** @var bool */
    protected $_canUseInternal = true;
    /** @var bool */
    protected $_canUseCheckout = true;
    /** @var bool */
    protected $_canUseForMultishipping = false;
    /** @var bool */
    protected $_isGateway = false;
    /** @var bool */
    protected $_canAuthorize = false;
    /** @var bool */
    protected $_canCapture = true;
    /** @var bool */
    protected $_canRefund = true;

    /**
     * Check if payment method is available
     * This payment method is only available when gift cards fully cover the order
     *
     * @param Mage_Sales_Model_Quote|null $quote
     * @return bool
     */
    #[\Override]
    public function isAvailable($quote = null)
    {
        // Skip parent check since we're inactive by default
        // We handle availability ourselves based on gift card coverage

        if (!$quote) {
            return false;
        }

        // Check if gift cards are applied and fully cover the order
        $giftcardAmount = abs((float) $quote->getGiftcardAmount());
        $grandTotal = (float) $quote->getGrandTotal();

        // Only available when gift cards fully cover the order
        return $giftcardAmount > 0 && $grandTotal <= 0.01;
    }

    /**
     * Check if payment method can be used for zero total
     *
     * @return bool
     */
    public function canUseForZeroTotal()
    {
        return true;
    }

    /**
     * Get config payment action
     *
     * @return string
     */
    #[\Override]
    public function getConfigPaymentAction()
    {
        return self::ACTION_AUTHORIZE_CAPTURE;
    }

    /**
     * Authorize payment - not used for gift cards
     *
     * @param float $amount
     * @return $this
     */
    #[\Override]
    public function authorize(Maho\DataObject $payment, $amount)
    {
        return $this;
    }

    /**
     * Capture payment - gift card balance is already deducted by the Observer
     * when the order is placed. This method is a no-op since the gift card
     * works as a discount that reduces the grand total to $0.
     *
     * @param float $amount
     * @return $this
     */
    #[\Override]
    public function capture(Maho\DataObject $payment, $amount)
    {
        // Gift card balance deduction is handled by deductGiftcardBalance observer
        // on sales_order_place_after event. The gift card acts as a discount
        // reducing the grand total, not as a traditional payment method.
        return $this;
    }

    /**
     * Refund payment - gift card balance restoration is handled by the
     * refundGiftcardBalance observer on sales_order_creditmemo_refund event.
     * This method is a no-op to avoid double refunding.
     *
     * @param float $amount
     * @return $this
     */
    #[\Override]
    public function refund(Maho\DataObject $payment, $amount)
    {
        // Gift card balance restoration is handled by Observer::refundGiftcardBalance()
        // on the sales_order_creditmemo_refund event, which has access to the
        // credit memo's gift card amount for accurate proportional refunds.
        return $this;
    }
}
