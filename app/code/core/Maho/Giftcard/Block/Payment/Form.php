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
 * Gift Card Payment Form Block
 */
class Maho_Giftcard_Block_Payment_Form extends Mage_Payment_Block_Form
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('maho/giftcard/payment/form.phtml');
    }

    /**
     * Get payment method title with additional message
     */
    public function getMethodTitle(): string
    {
        return $this->__('This order will be fully paid by the gift card(s)');
    }

    /**
     * Get the current quote (handles both frontend and admin contexts)
     */
    protected function getQuote(): Mage_Sales_Model_Quote
    {
        if (Mage::app()->getStore()->isAdmin()) {
            return Mage::getSingleton('adminhtml/session_quote')->getQuote();
        }
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    /**
     * Get applied gift card codes with amounts
     */
    public function getAppliedGiftcards(): array
    {
        $quote = $this->getQuote();
        $codesJson = $quote->getGiftcardCodes();

        if ($codesJson === null || $codesJson === '') {
            return [];
        }

        $codes = json_decode($codesJson, true) ?: [];
        $result = [];

        foreach ($codes as $code => $amount) {
            $result[] = [
                'code' => $this->maskGiftcardCode($code),
                'amount' => Mage::helper('core')->currency($amount, true, false),
            ];
        }

        return $result;
    }

    /**
     * Mask gift card code for display
     */
    protected function maskGiftcardCode(string $code): string
    {
        $length = strlen($code);
        if ($length <= 8) {
            return $code;
        }

        return substr($code, 0, 4) . str_repeat('*', $length - 8) . substr($code, -4);
    }
}
