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
 * Checkout Payment Methods Block
 * Handles special logic for gift card payment method visibility
 */
class Maho_Giftcard_Block_Checkout_Onepage_Payment_Methods extends Mage_Checkout_Block_Onepage_Payment_Methods
{
    /**
     * Get available payment methods
     * Filters methods based on gift card coverage
     *
     * @return Mage_Payment_Model_Method_Abstract[]
     */
    #[\Override]
    public function getMethods()
    {
        $methods = parent::getMethods();
        $quote = $this->getQuote();

        if (!$quote) {
            return $methods;
        }

        // Check if order is fully covered by gift cards
        if ($this->isFullyCoveredByGiftcard($quote)) {
            // Auto-select gift card payment method if it's the only one
            $giftcardMethod = null;
            foreach ($methods as $method) {
                if ($method->getCode() === 'maho_giftcard') {
                    $giftcardMethod = $method;
                    break;
                }
            }

            // If gift card payment is available and order is fully covered,
            // automatically select it
            if ($giftcardMethod && count($methods) === 1) {
                $this->setSelectedMethodCode('maho_giftcard');
            }
        }

        return $methods;
    }

    /**
     * Check if order is fully covered by gift cards
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return bool
     */
    protected function isFullyCoveredByGiftcard(Mage_Sales_Model_Quote $quote): bool
    {
        $giftcardAmount = abs((float)$quote->getGiftcardAmount());
        $grandTotal = (float)$quote->getGrandTotal();

        return $giftcardAmount > 0 && $grandTotal <= 0.01;
    }

    /**
     * Get selected payment method code
     *
     * @return string|null
     */
    public function getSelectedMethodCode()
    {
        $quote = $this->getQuote();

        // If order is fully covered by gift cards, auto-select gift card payment
        if ($this->isFullyCoveredByGiftcard($quote)) {
            return 'maho_giftcard';
        }

        $method = $this->getQuote()->getPayment()->getMethod();
        if ($method) {
            return $method;
        }

        return parent::getSelectedMethodCode();
    }
}