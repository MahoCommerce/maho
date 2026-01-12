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
 * Gift card block for shopping cart
 */
class Maho_Giftcard_Block_Checkout_Cart_Giftcard extends Mage_Core_Block_Template
{
    /**
     * Get applied gift card codes from quote
     */
    public function getAppliedGiftcardCodes(): array
    {
        $quote = $this->getQuote();
        $codes = $quote->getGiftcardCodes();

        if (!$codes) {
            return [];
        }

        // Decode JSON if it's not already an array
        if (is_string($codes)) {
            $codes = json_decode($codes, true);
        }

        return is_array($codes) ? $codes : [];
    }

    /**
     * Get quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    /**
     * Check if customer can apply gift cards
     */
    public function canApplyGiftcard(): bool
    {
        $quote = $this->getQuote();

        // Check if cart has gift card products
        // (Gift cards cannot be purchased with gift cards)
        foreach ($quote->getAllItems() as $item) {
            if ($item->getProductType() === 'giftcard') {
                return false;
            }
        }

        return true;
    }
}
