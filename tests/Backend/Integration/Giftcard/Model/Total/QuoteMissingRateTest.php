<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * The totals collector runs on every cart render and has nobody to catch it, so a card it
 * cannot value stays applied and discounts nothing. The quote is priced in an ISO 4217 "X"
 * code no real currency uses, so no rate reaches the card from either direction.
 */
const GIFTCARD_QUOTE_CURRENCY = 'XTN';

function giftcardForQuoteTotal(float $balance): Maho_Giftcard_Model_Giftcard
{
    /** @var Maho_Giftcard_Model_Giftcard $giftcard */
    $giftcard = Mage::getModel('giftcard/giftcard');
    $giftcard->setCode(Mage::helper('giftcard')->generateCode())
        ->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
        ->setWebsiteIds([1])
        ->setBalance($balance)
        ->setInitialBalance($balance)
        ->save();

    return $giftcard;
}

it('leaves a card it cannot value applied, and collects the rest of the quote', function () {
    $giftcard = giftcardForQuoteTotal(100.0);

    try {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1)
            ->setBaseCurrencyCode(GIFTCARD_QUOTE_CURRENCY)
            ->setGiftcardCodes((string) json_encode([$giftcard->getCode() => 0]));

        $address = $quote->getShippingAddress();
        $address->setBaseSubtotal(100.0)
            ->setSubtotal(100.0)
            ->setBaseDiscountAmount(0.0)
            ->setDiscountAmount(0.0)
            ->setBaseShippingAmount(0.0)
            ->setShippingAmount(0.0)
            ->setBaseTaxAmount(0.0)
            ->setTaxAmount(0.0);

        Mage::getModel('giftcard/total_quote')->collect($address);

        expect((float) $address->getBaseGiftcardAmount())->toBe(0.0);
        expect((float) $address->getGiftcardAmount())->toBe(0.0);
        expect(json_decode((string) $quote->getGiftcardCodes(), true))->toBe([$giftcard->getCode() => 0]);
    } finally {
        $giftcard->delete();
    }
});
