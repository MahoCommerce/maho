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
 * Gift card block for checkout payment step
 */
class Maho_Giftcard_Block_Checkout_Payment_Giftcard extends Mage_Core_Block_Template
{
    /**
     * Get applied gift card codes from quote
     *
     * @return array<string, array{amount: float, balance: float, display_code: string}>
     */
    public function getAppliedGiftcards(): array
    {
        $quote = $this->getQuote();
        $codes = $quote->getGiftcardCodes();

        if (!$codes) {
            return [];
        }

        if (is_string($codes)) {
            $codes = json_decode($codes, true);
        }

        if (!is_array($codes) || $codes === []) {
            return [];
        }

        $result = [];
        $quoteCurrency = $quote->getQuoteCurrencyCode();

        // Get the total display amount already calculated by the totals collector
        // This is already converted to display currency
        $totalDisplayAmount = abs((float) $quote->getGiftcardAmount());
        $totalBaseAmount = abs((float) $quote->getBaseGiftcardAmount());

        // Calculate the ratio to distribute display amounts proportionally
        $ratio = $totalBaseAmount > 0 ? $totalDisplayAmount / $totalBaseAmount : 0;

        foreach ($codes as $code => $baseAppliedAmount) {
            $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode($code);
            if ($giftcard->getId()) {
                // Use the ratio to calculate display amount (avoids re-conversion)
                $displayAmount = (float) $baseAppliedAmount * $ratio;
                $result[$code] = [
                    'amount' => $displayAmount,
                    'balance' => $giftcard->getBalance($quoteCurrency),
                    'display_code' => Mage::helper('giftcard')->maskCode($code),
                ];
            }
        }

        return $result;
    }

    /**
     * Get quote
     */
    public function getQuote(): Mage_Sales_Model_Quote
    {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    /**
     * Get total gift card amount applied
     */
    public function getTotalGiftcardAmount(): float
    {
        return abs((float) $this->getQuote()->getGiftcardAmount());
    }

    /**
     * Get base total gift card amount applied
     */
    public function getBaseTotalGiftcardAmount(): float
    {
        return abs((float) $this->getQuote()->getBaseGiftcardAmount());
    }

    /**
     * Get remaining amount to pay (grand total after gift card)
     */
    public function getAmountDue(): float
    {
        $grandTotal = (float) $this->getQuote()->getGrandTotal();
        return max(0, $grandTotal);
    }

    /**
     * Get subtotal before gift card
     */
    public function getSubtotalBeforeGiftcard(): float
    {
        return $this->getAmountDue() + $this->getTotalGiftcardAmount();
    }

    /**
     * Check if order is fully covered by gift cards
     */
    public function isFullyCovered(): bool
    {
        $giftcardAmount = $this->getTotalGiftcardAmount();
        $grandTotal = (float) $this->getQuote()->getGrandTotal();

        return $giftcardAmount > 0 && $grandTotal <= 0.01;
    }

    /**
     * Check if customer can apply gift cards
     */
    public function canApplyGiftcard(): bool
    {
        $quote = $this->getQuote();

        // Check if cart has gift card products
        foreach ($quote->getAllItems() as $item) {
            if ($item->getProductType() === 'giftcard') {
                return false;
            }
        }

        return true;
    }

    /**
     * Get apply gift card URL (AJAX)
     */
    public function getApplyUrl(): string
    {
        return $this->getUrl('giftcard/cart/ajaxApply');
    }

    /**
     * Get remove gift card URL (AJAX)
     */
    public function getRemoveUrl(): string
    {
        return $this->getUrl('giftcard/cart/ajaxRemove');
    }

    /**
     * Format price (amount is already in display currency, no conversion needed)
     */
    public function formatPrice(float $amount): string
    {
        return $this->getQuote()->getStore()->formatPrice($amount, false);
    }
}
