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

class Maho_Giftcard_Block_Adminhtml_Sales_Order_Create_Giftcard extends Mage_Adminhtml_Block_Sales_Order_Create_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('sales_order_create_giftcard_form');
    }

    /**
     * Get applied gift card codes with their data
     */
    public function getAppliedGiftcards(): array
    {
        $giftcards = [];
        $quote = $this->getQuote();
        $appliedCodes = $quote->getGiftcardCodes();

        if (!$appliedCodes) {
            return $giftcards;
        }

        $codes = json_decode($appliedCodes, true);
        if (!is_array($codes)) {
            return $giftcards;
        }

        $baseCurrency = $quote->getBaseCurrencyCode();

        foreach ($codes as $code => $appliedAmount) {
            $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode($code);

            if ($giftcard->getId()) {
                $balance = $giftcard->getBalance($baseCurrency);
                $giftcards[] = [
                    'code' => $code,
                    'display_code' => $code,
                    'applied_amount' => (float) $appliedAmount,
                    'applied_amount_formatted' => $this->formatPrice($appliedAmount),
                    'balance' => $balance,
                    'balance_formatted' => $this->formatPrice($balance),
                    'status' => $giftcard->getStatusLabel(),
                ];
            }
        }

        return $giftcards;
    }

    /**
     * Get total applied gift card amount
     */
    public function getTotalGiftcardAmount(): float
    {
        return abs((float) $this->getQuote()->getBaseGiftcardAmount());
    }

    /**
     * Get formatted total gift card amount
     */
    public function getTotalGiftcardAmountFormatted(): string
    {
        return $this->formatPrice($this->getTotalGiftcardAmount());
    }

    /**
     * Check if gift cards are applied
     */
    public function hasAppliedGiftcards(): bool
    {
        return !empty($this->getAppliedGiftcards());
    }

    /**
     * Get header text
     */
    public function getHeaderText(): string
    {
        return Mage::helper('giftcard')->__('Apply Gift Card');
    }

    /**
     * Get header CSS class
     */
    public function getHeaderCssClass(): string
    {
        return 'head-promo-quote';
    }

    /**
     * Check if module is enabled
     */
    public function isEnabled(): bool
    {
        return Mage::helper('giftcard')->isEnabled();
    }

    /**
     * Check if cart contains gift card products (cannot pay with gift card)
     */
    public function hasGiftcardProducts(): bool
    {
        foreach ($this->getQuote()->getAllItems() as $item) {
            if ($item->getProductType() === 'giftcard') {
                return true;
            }
        }
        return false;
    }
}
