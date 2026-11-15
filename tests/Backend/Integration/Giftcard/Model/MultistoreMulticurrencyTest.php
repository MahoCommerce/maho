<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Helper function to create a mock quote item for testing
 */
function createTestQuoteItem(array $data): Maho\DataObject
{
    return new Maho\DataObject(array_merge([
        'parent_item_id' => null,
        'product_type' => 'simple',
        'base_row_total_incl_tax' => 0,
        'row_total_incl_tax' => 0,
        'base_discount_amount' => 0,
        'discount_amount' => 0,
    ], $data));
}

/**
 * Helper function to set mock items on an address
 */
function setQuoteAddressItems(Mage_Sales_Model_Quote_Address $address, array $items): void
{
    $address->setData('cached_items_all', $items);
}

describe('Multistore Gift Card Isolation', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');

        // Create a second website for testing
        $this->testWebsite = Mage::getModel('core/website');
        $this->testWebsite->setCode('test_website_' . uniqid());
        $this->testWebsite->setName('Test Website');
        $this->testWebsite->save();

        // Create gift card for website 1
        $this->cardWebsite1 = Mage::getModel('giftcard/giftcard');
        $this->cardWebsite1->setCode($this->helper->generateCode());
        $this->cardWebsite1->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->cardWebsite1->setWebsiteIds([1]);
        $this->cardWebsite1->setBalance(100.00);
        $this->cardWebsite1->setInitialBalance(100.00);
        $this->cardWebsite1->save();

        // Create gift card for the test website
        $this->cardWebsite2 = Mage::getModel('giftcard/giftcard');
        $this->cardWebsite2->setCode($this->helper->generateCode());
        $this->cardWebsite2->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->cardWebsite2->setWebsiteIds([$this->testWebsite->getId()]);
        $this->cardWebsite2->setBalance(75.00);
        $this->cardWebsite2->setInitialBalance(75.00);
        $this->cardWebsite2->save();
    });

    afterEach(function () {
        // Clean up test website
        if ($this->testWebsite && $this->testWebsite->getId()) {
            $this->testWebsite->delete();
        }
    });

    test('gift card validation respects website boundaries during quote totals', function () {
        // Create quote for store 1 (website 1)
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        // Apply card from website 1
        $codes = [$this->cardWebsite1->getCode() => 0];
        $quote->setGiftcardCodes(json_encode($codes));

        $address = $quote->getShippingAddress();
        $address->setBaseSubtotal(50.00);
        $address->setSubtotal(50.00);
        $address->setBaseShippingInclTax(0);
        $address->setShippingInclTax(0);

        $item = createTestQuoteItem([
            'base_row_total_incl_tax' => 50.00,
            'row_total_incl_tax' => 50.00,
        ]);
        setQuoteAddressItems($address, [$item]);

        // Collect totals
        $total = Mage::getModel('giftcard/total_quote');
        $total->collect($address);

        // Card should be applied (website matches)
        expect((float) $address->getBaseGiftcardAmount())->toBe(50.00);
    });

    test('gift card from different website is filtered out during quote total collection', function () {
        // Create quote for store 1 (website 1)
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        // Try to apply card from test website to website 1 quote
        $codes = [$this->cardWebsite2->getCode() => 0];
        $quote->setGiftcardCodes(json_encode($codes));

        $address = $quote->getShippingAddress();
        $address->setBaseSubtotal(50.00);
        $address->setSubtotal(50.00);
        $address->setBaseShippingInclTax(0);
        $address->setShippingInclTax(0);

        $item = createTestQuoteItem([
            'base_row_total_incl_tax' => 50.00,
            'row_total_incl_tax' => 50.00,
        ]);
        setQuoteAddressItems($address, [$item]);

        // Collect totals
        $total = Mage::getModel('giftcard/total_quote');
        $total->collect($address);

        // Card should NOT be applied (website mismatch)
        expect((float) $address->getBaseGiftcardAmount())->toBe(0.0);

        // Check if invalid card was removed from address codes
        $addressCodes = json_decode($address->getGiftcardCodes() ?: '{}', true);
        expect($addressCodes)->not->toHaveKey($this->cardWebsite2->getCode());
    });

    test('multiple cards from different websites only valid card applies', function () {
        // Create quote for store 1 (website 1)
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        // Try to apply both cards (one valid, one from different website)
        $codes = [
            $this->cardWebsite1->getCode() => 0, // Valid for website 1
            $this->cardWebsite2->getCode() => 0, // Invalid - from test website
        ];
        $quote->setGiftcardCodes(json_encode($codes));

        $address = $quote->getShippingAddress();
        $address->setBaseSubtotal(200.00);
        $address->setSubtotal(200.00);
        $address->setBaseShippingInclTax(0);
        $address->setShippingInclTax(0);

        $item = createTestQuoteItem([
            'base_row_total_incl_tax' => 200.00,
            'row_total_incl_tax' => 200.00,
        ]);
        setQuoteAddressItems($address, [$item]);

        // Collect totals
        $total = Mage::getModel('giftcard/total_quote');
        $total->collect($address);

        // Only website 1 card should be applied (100.00, not 175.00)
        expect((float) $address->getBaseGiftcardAmount())->toBe(100.00);

        // The invalid card should be removed
        $updatedCodes = json_decode($quote->getGiftcardCodes(), true);
        expect($updatedCodes)->toHaveKey($this->cardWebsite1->getCode());
        expect($updatedCodes)->not->toHaveKey($this->cardWebsite2->getCode());
    });
});

describe('Multicurrency Gift Card Conversion', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');

        // Create gift card with balance in base currency (assume USD for website 1)
        $this->giftcard = Mage::getModel('giftcard/giftcard');
        $this->giftcard->setCode($this->helper->generateCode());
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setWebsiteIds([1]);
        $this->giftcard->setBalance(100.00); // Stored in website's base currency
        $this->giftcard->setInitialBalance(100.00);
        $this->giftcard->save();
    });

    test('gift card balance converts to another currency at the rate of the store', function () {
        $rate = useEurDisplayCurrency();

        try {
            expect($this->giftcard->getBalance('EUR'))->toEqualWithDelta(100.00 * $rate, 0.0001);
        } finally {
            resetCurrencyState();
        }
    });

    test('gift card getCurrencyCode returns website base currency', function () {
        $website = $this->giftcard->getWebsite();
        $expectedCurrency = $website->getBaseCurrencyCode();

        expect($this->giftcard->getCurrencyCode())->toBe($expectedCurrency);
    });
});

describe('Multicurrency Quote Total Collection', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');

        // Create gift card (stored in base currency of website 1)
        $this->giftcard = Mage::getModel('giftcard/giftcard');
        $this->giftcard->setCode($this->helper->generateCode());
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setWebsiteIds([1]);
        $this->giftcard->setBalance(100.00); // In base currency
        $this->giftcard->setInitialBalance(100.00);
        $this->giftcard->save();
    });

    test('gift card balance is properly tracked per currency', function () {
        // Create order with different currency conversion
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);

        $store = Mage::app()->getStore(1);
        $baseCurrency = $store->getBaseCurrencyCode();

        // Set display currency same as base for this test
        $quote->setQuoteCurrencyCode($baseCurrency);
        $quote->setBaseCurrencyCode($baseCurrency);
        $quote->setStoreToBaseRate(1.0);
        $quote->setStoreToQuoteRate(1.0);
        $quote->save();

        $codes = [$this->giftcard->getCode() => 0];
        $quote->setGiftcardCodes(json_encode($codes));

        $address = $quote->getShippingAddress();
        $address->setBaseSubtotal(150.00);
        $address->setSubtotal(150.00);
        $address->setBaseShippingInclTax(0);
        $address->setShippingInclTax(0);

        $item = createTestQuoteItem([
            'base_row_total_incl_tax' => 150.00,
            'row_total_incl_tax' => 150.00,
        ]);
        setQuoteAddressItems($address, [$item]);

        $total = Mage::getModel('giftcard/total_quote');
        $total->collect($address);

        // Full card balance should apply (100.00)
        expect((float) $address->getBaseGiftcardAmount())->toBe(100.00);

        // Verify the amount is tracked correctly in the codes
        $updatedCodes = json_decode($address->getGiftcardCodes(), true);
        expect($updatedCodes)->toHaveKey($this->giftcard->getCode());
        expect((float) $updatedCodes[$this->giftcard->getCode()])->toBe(100.00);
    });
});
