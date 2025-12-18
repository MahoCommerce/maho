<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Gift Card Payment Method
 */
class Maho_Giftcard_Model_Payment extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'maho_giftcard';
    protected $_formBlockType = 'maho_giftcard/payment_form';
    protected $_infoBlockType = 'maho_giftcard/payment_info';
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_isGateway = false;
    protected $_canAuthorize = false;
    protected $_canCapture = true;
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
    public function getConfigPaymentAction()
    {
        return self::ACTION_AUTHORIZE_CAPTURE;
    }

    /**
     * Authorize payment - not used for gift cards
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     */
    #[\Override]
    public function authorize(Varien_Object $payment, $amount)
    {
        return $this;
    }

    /**
     * Capture payment - deduct from gift card balances
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     */
    #[\Override]
    public function capture(Varien_Object $payment, $amount)
    {
        $order = $payment->getOrder();
        $codes = $this->_getAppliedCodes($order);

        if (empty($codes)) {
            return $this;
        }

        foreach ($codes as $code => $appliedAmount) {
            $giftcard = Mage::getModel('maho_giftcard/giftcard')->loadByCode($code);

            if ($giftcard->getId() && $giftcard->isValid()) {
                $giftcard->use(
                    (float) $appliedAmount,
                    (int) $order->getId(),
                    "Payment for order #{$order->getIncrementId()}",
                );
            }
        }

        return $this;
    }

    /**
     * Refund payment - add back to gift card balances
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     */
    #[\Override]
    public function refund(Varien_Object $payment, $amount)
    {
        $order = $payment->getOrder();
        $codes = $this->_getAppliedCodes($order);

        if (empty($codes)) {
            return $this;
        }

        // Refund proportionally to each gift card used
        $totalApplied = array_sum($codes);

        foreach ($codes as $code => $appliedAmount) {
            $giftcard = Mage::getModel('maho_giftcard/giftcard')->loadByCode($code);

            if ($giftcard->getId()) {
                $refundAmount = ($appliedAmount / $totalApplied) * $amount;
                $giftcard->refund(
                    $refundAmount,
                    (int) $order->getId(),
                    "Refund for order #{$order->getIncrementId()}",
                );
            }
        }

        return $this;
    }

    /**
     * Get applied gift card codes from order
     *
     * @param Mage_Sales_Model_Order $order
     * @return array [code => amount]
     */
    protected function _getAppliedCodes(Mage_Sales_Model_Order $order): array
    {
        $codesJson = $order->getGiftcardCodes();

        if (empty($codesJson)) {
            return [];
        }

        return json_decode($codesJson, true) ?: [];
    }
}
