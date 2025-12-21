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

        if (empty($codesJson)) {
            return $this;
        }

        $codes = json_decode($codesJson, true);

        if (empty($codes)) {
            return $this;
        }

        // Get the total from all collected totals so far
        if ($address->getAllBaseTotalAmounts()) {
            $totalAmounts = $address->getAllBaseTotalAmounts();
            $baseGrandTotal = array_sum($totalAmounts);
        } else {
            $baseGrandTotal = $address->getBaseGrandTotal();
        }
        $grandTotal = $address->getGrandTotal();

        $baseTotalDiscount = 0;
        $totalDiscount = 0;

        // Apply each gift card
        $validCodes = [];

        foreach ($codes as $code => $requestedAmount) {
            $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode($code);

            if (!$giftcard->getId() || !$giftcard->isValid()) {
                continue; // Skip invalid cards
            }

            // Calculate how much can be applied
            $remainingTotal = $baseGrandTotal - $baseTotalDiscount;

            if ($remainingTotal <= 0) {
                break; // Nothing left to pay
            }

            $availableBalance = $giftcard->getBalance();
            // If no specific amount requested (0), apply max available
            if ($requestedAmount <= 0) {
                $baseAmountToApply = min($availableBalance, $remainingTotal);
            } else {
                $baseAmountToApply = min($requestedAmount, $availableBalance, $remainingTotal);
            }

            if ($baseAmountToApply > 0) {
                $baseTotalDiscount += $baseAmountToApply;
                $totalDiscount += $quote->getStore()->convertPrice($baseAmountToApply);

                $validCodes[$code] = $baseAmountToApply;
            }
        }

        if ($baseTotalDiscount > 0) {
            // Store amounts on both quote and address as POSITIVE values
            $quote->setBaseGiftcardAmount($baseTotalDiscount);
            $quote->setGiftcardAmount($totalDiscount);
            $address->setBaseGiftcardAmount($baseTotalDiscount);
            $address->setGiftcardAmount($totalDiscount);

            // Store gift card codes on address for display
            $address->setGiftcardCodes(json_encode($validCodes));

            // Update valid codes (remove invalid ones)
            $quote->setGiftcardCodes(json_encode($validCodes));

            // DON'T modify grand total directly - let _addAmount handle it
            // $address->setBaseGrandTotal($address->getBaseGrandTotal() - $baseTotalDiscount);
            // $address->setGrandTotal($address->getGrandTotal() - $totalDiscount);

            // Reset the total amounts before adding (to prevent accumulation)
            $address->setTotalAmount($this->getCode(), 0);
            $address->setBaseTotalAmount($this->getCode(), 0);

            // Use negative amounts for the total collector
            // This will handle updating the grand total properly
            $this->_addAmount(-$totalDiscount);
            $this->_addBaseAmount(-$baseTotalDiscount);
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
        $amount = $address->getGiftcardAmount();


        if ($amount != 0) {
            // Get gift card codes for display
            $giftcardCodes = $address->getGiftcardCodes();
            if (!$giftcardCodes) {
                $giftcardCodes = $address->getQuote()->getGiftcardCodes();
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
