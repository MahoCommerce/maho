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
 * Gift Card Payment Helper - Filters payment methods based on gift card coverage
 */
class Maho_Giftcard_Helper_Payment extends Mage_Payment_Helper_Data
{
    /**
     * Retrieve available payment methods for store
     * Filters payment methods when gift cards fully cover the order
     *
     * @param null|string|bool|int|Mage_Core_Model_Store $store
     * @param Mage_Sales_Model_Quote $quote
     * @return Mage_Payment_Model_Method_Abstract[]
     */
    #[\Override]
    public function getStoreMethods($store = null, $quote = null)
    {
        // Get all available payment methods from parent
        $methods = parent::getStoreMethods($store, $quote);

        // Check if gift cards fully cover the order
        if ($quote && $this->isFullyCoveredByGiftcard($quote)) {
            // Filter to only show gift card payment method
            $filteredMethods = [];
            foreach ($methods as $method) {
                if ($method->getCode() === 'giftcard' ||
                    $method->getCode() === 'free') {
                    $filteredMethods[] = $method;
                }
            }

            // If we have the gift card payment method, use only that
            if (!empty($filteredMethods)) {
                return $filteredMethods;
            }
        } elseif ($quote && $this->hasPartialGiftcardCoverage($quote)) {
            // For partial coverage, exclude the gift card payment method
            $filteredMethods = [];
            foreach ($methods as $method) {
                if ($method->getCode() !== 'giftcard') {
                    $filteredMethods[] = $method;
                }
            }
            return $filteredMethods;
        }

        return $methods;
    }

    /**
     * Check if order is fully covered by gift cards
     */
    protected function isFullyCoveredByGiftcard(Mage_Sales_Model_Quote $quote): bool
    {
        $giftcardAmount = abs((float) $quote->getGiftcardAmount());
        $grandTotal = (float) $quote->getGrandTotal();

        return $giftcardAmount > 0 && $grandTotal <= 0.01;
    }

    /**
     * Check if order has partial gift card coverage
     */
    protected function hasPartialGiftcardCoverage(Mage_Sales_Model_Quote $quote): bool
    {
        $giftcardAmount = abs((float) $quote->getGiftcardAmount());
        $grandTotal = (float) $quote->getGrandTotal();

        return $giftcardAmount > 0 && $grandTotal > 0.01;
    }
}
