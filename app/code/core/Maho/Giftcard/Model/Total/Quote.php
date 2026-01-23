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
 * Gift Card Quote Total
 */
class Maho_Giftcard_Model_Total_Quote extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    public function __construct()
    {
        $this->setCode('giftcard');
    }

    /**
     * Collect gift card totals
     *
     * @return $this
     */
    #[\Override]
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        parent::collect($address);

        // Reset gift card amounts
        $address->setGiftcardAmount(0);
        $address->setBaseGiftcardAmount(0);

        $quote = $address->getQuote();

        // Only apply to billing address for virtual quotes, shipping for others
        $addressType = $address->getAddressType();
        if ($addressType == 'billing' && !$quote->isVirtual()) {
            return $this;
        }
        if ($addressType == 'shipping' && $quote->isVirtual()) {
            return $this;
        }

        $codesJson = $quote->getGiftcardCodes();

        if ($codesJson === null || $codesJson === '') {
            return $this;
        }

        $codes = json_decode($codesJson, true);

        if (!is_array($codes) || $codes === []) {
            return $this;
        }

        // Calculate eligible total from address totals (subtotal + discount + shipping + tax)
        // Note: discount amount is already negative, so we ADD it
        $baseGrandTotal = $address->getBaseSubtotal()
            + $address->getBaseDiscountAmount()
            + $address->getBaseShippingAmount()
            + $address->getBaseTaxAmount();

        $grandTotal = $address->getSubtotal()
            + $address->getDiscountAmount()
            + $address->getShippingAmount()
            + $address->getTaxAmount();

        // Exclude gift card products from gift card payment (to prevent circular purchases)
        foreach ($address->getAllItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }
            if ($item->getProductType() === 'giftcard') {
                // Remove this item's contribution to the eligible total
                $baseGrandTotal -= ($item->getBaseRowTotal() - $item->getBaseDiscountAmount() + $item->getBaseTaxAmount());
                $grandTotal -= ($item->getRowTotal() - $item->getDiscountAmount() + $item->getTaxAmount());
            }
        }

        $baseTotalDiscount = 0;
        $totalDiscount = 0;

        // Apply each gift card
        $validCodes = [];
        $websiteId = (int) $quote->getStore()->getWebsiteId();
        $quoteBaseCurrency = $quote->getBaseCurrencyCode();

        foreach (array_keys($codes) as $code) {
            $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode($code);

            if (!$giftcard->getId() || !$giftcard->isValidForWebsite($websiteId)) {
                continue; // Skip invalid cards or cards from different website
            }

            // Calculate how much can be applied
            $remainingTotal = $baseGrandTotal - $baseTotalDiscount;

            if ($remainingTotal <= 0) {
                break; // Nothing left to pay
            }

            // Get gift card balance converted to quote's base currency
            // Always use full available balance (no partial usage support)
            $availableBalance = $giftcard->getBalance($quoteBaseCurrency);
            $baseAmountToApply = min($availableBalance, $remainingTotal);

            if ($baseAmountToApply > 0) {
                $baseTotalDiscount += $baseAmountToApply;
                $totalDiscount += $quote->getStore()->convertPrice($baseAmountToApply);

                $validCodes[$code] = $baseAmountToApply;
            }
        }

        if ($baseTotalDiscount > 0) {
            // Store gift card codes on address for display
            $address->setGiftcardCodes(json_encode($validCodes));

            // Update valid codes (remove invalid ones)
            $quote->setGiftcardCodes(json_encode($validCodes));

            // Reset total amounts before setting (to prevent accumulation from multiple calls)
            $address->setTotalAmount($this->getCode(), 0);
            $address->setBaseTotalAmount($this->getCode(), 0);

            // Add negative amounts - Grand collector will sum these into grand_total
            $this->_addAmount(-$totalDiscount);
            $this->_addBaseAmount(-$baseTotalDiscount);

            // Store amounts on both quote and address as POSITIVE values
            // Note: Must be set AFTER setBaseTotalAmount() since that method also sets base_giftcard_amount
            $quote->setBaseGiftcardAmount($baseTotalDiscount);
            $quote->setGiftcardAmount($totalDiscount);
            $address->setBaseGiftcardAmount($baseTotalDiscount);
            $address->setGiftcardAmount($totalDiscount);
        } else {
            // No gift card discount - reset amounts
            $address->setBaseGiftcardAmount(0);
            $address->setGiftcardAmount(0);
            $address->setTotalAmount($this->getCode(), 0);
            $address->setBaseTotalAmount($this->getCode(), 0);
        }

        return $this;
    }

    /**
     * Add total information to address
     *
     * @return $this
     */
    #[\Override]
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $quote = $address->getQuote();

        // Only add total to the appropriate address type to avoid doubling
        $addressType = $address->getAddressType();
        if ($addressType == 'billing' && !$quote->isVirtual()) {
            return $this;
        }
        if ($addressType == 'shipping' && $quote->isVirtual()) {
            return $this;
        }

        // Get gift card amount from address (set by collect)
        $amount = $address->getGiftcardAmount();
        $giftcardCodes = $address->getGiftcardCodes();

        // If not set on address, use stored amounts from quote codes
        if (!$amount && $quote->getGiftcardCodes()) {
            $codesJson = $quote->getGiftcardCodes();
            $codes = json_decode($codesJson, true);

            if (is_array($codes) && $codes !== []) {
                // The codes array stores [code => baseAppliedAmount]
                // Sum the stored applied amounts (already capped to order total)
                $baseAmount = array_sum($codes);
                $amount = $quote->getStore()->convertPrice($baseAmount);
                $giftcardCodes = $codesJson;
            }
        }

        if ($amount != 0) {
            // Get gift card codes for display
            if (!$giftcardCodes) {
                $giftcardCodes = $quote->getGiftcardCodes();
            }

            $codes = [];
            if ($giftcardCodes) {
                $codesArray = json_decode($giftcardCodes, true);
                if (is_array($codesArray)) {
                    $codes = array_keys($codesArray);
                }
            }

            $address->addTotal([
                'code' => $this->getCode(),
                'title' => Mage::helper('giftcard')->__('Gift Certificates'),
                'value' => $amount, // Pass positive value - template will handle display
                'giftcard_codes' => implode(', ', $codes),
            ]);
        }

        return $this;
    }
}
