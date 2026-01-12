<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Helper function to create a mock quote item for testing
 */
function createMockQuoteItem(array $data): Maho\DataObject
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
 * Uses the internal caching mechanism of getAllItems()
 */
function setAddressItems(Mage_Sales_Model_Quote_Address $address, array $items): void
{
    // Set items in the cache keys used by getAllItems()
    $address->setData('cached_items_all', $items);
    $address->setData('cached_items_nonnominal', $items);
    $address->setData('cached_items_nominal', []);
}

describe('Quote Total Collector Instantiation', function () {
    test('total collector can be instantiated', function () {
        $total = Mage::getModel('giftcard/total_quote');
        expect($total)->toBeInstanceOf(Maho_Giftcard_Model_Total_Quote::class);
        expect($total)->toBeInstanceOf(Mage_Sales_Model_Quote_Address_Total_Abstract::class);
    });

    test('has correct code', function () {
        $total = Mage::getModel('giftcard/total_quote');
        expect($total->getCode())->toBe('giftcard');
    });
});

describe('Quote Total Collection with Gift Cards', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');

        // Create a test gift card
        $this->giftcard = Mage::getModel('giftcard/giftcard');
        $this->giftcard->setCode($this->helper->generateCode());
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setWebsiteId(1);
        $this->giftcard->setBalance(100.00);
        $this->giftcard->setInitialBalance(100.00);
        $this->giftcard->save();

        // Create a quote
        $this->quote = Mage::getModel('sales/quote');
        $this->quote->setStoreId(1);
        $this->quote->save();
    });

    test('applies single gift card to quote totals', function () {
        // Apply gift card code
        $codes = [$this->giftcard->getCode() => 0];
        $this->quote->setGiftcardCodes(json_encode($codes));

        // Create a shipping address with items
        $address = $this->quote->getShippingAddress();
        $address->setBaseSubtotal(150.00);
        $address->setSubtotal(150.00);
        $address->setBaseShippingInclTax(10.00);
        $address->setShippingInclTax(10.00);
        $address->setBaseGrandTotal(160.00);
        $address->setGrandTotal(160.00);

        // Set mock items using the cache mechanism
        $item = createMockQuoteItem([
            'base_row_total_incl_tax' => 150.00,
            'row_total_incl_tax' => 150.00,
        ]);
        setAddressItems($address, [$item]);

        // Collect totals
        $total = Mage::getModel('giftcard/total_quote');
        $total->collect($address);

        // Gift card should be applied (min of card balance and eligible total)
        expect((float) $address->getBaseGiftcardAmount())->toBe(100.00);
        expect((float) $address->getGiftcardAmount())->toBe(100.00);
    });

    test('applies partial gift card when balance less than total', function () {
        // Create a gift card with low balance
        $smallCard = Mage::getModel('giftcard/giftcard');
        $smallCard->setCode($this->helper->generateCode());
        $smallCard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $smallCard->setWebsiteId(1);
        $smallCard->setBalance(25.00);
        $smallCard->setInitialBalance(25.00);
        $smallCard->save();

        $codes = [$smallCard->getCode() => 0];
        $this->quote->setGiftcardCodes(json_encode($codes));

        $address = $this->quote->getShippingAddress();
        $address->setBaseSubtotal(100.00);
        $address->setSubtotal(100.00);
        $address->setBaseShippingInclTax(0);
        $address->setShippingInclTax(0);

        $item = createMockQuoteItem([
            'base_row_total_incl_tax' => 100.00,
            'row_total_incl_tax' => 100.00,
        ]);
        setAddressItems($address, [$item]);

        $total = Mage::getModel('giftcard/total_quote');
        $total->collect($address);

        // Only card balance should be applied
        expect((float) $address->getBaseGiftcardAmount())->toBe(25.00);
    });

    test('applies multiple gift cards cumulatively', function () {
        // Create second gift card
        $card2 = Mage::getModel('giftcard/giftcard');
        $card2->setCode($this->helper->generateCode());
        $card2->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $card2->setWebsiteId(1);
        $card2->setBalance(50.00);
        $card2->setInitialBalance(50.00);
        $card2->save();

        $codes = [
            $this->giftcard->getCode() => 0,
            $card2->getCode() => 0,
        ];
        $this->quote->setGiftcardCodes(json_encode($codes));

        $address = $this->quote->getShippingAddress();
        $address->setBaseSubtotal(200.00);
        $address->setSubtotal(200.00);
        $address->setBaseShippingInclTax(0);
        $address->setShippingInclTax(0);

        $item = createMockQuoteItem([
            'base_row_total_incl_tax' => 200.00,
            'row_total_incl_tax' => 200.00,
        ]);
        setAddressItems($address, [$item]);

        $total = Mage::getModel('giftcard/total_quote');
        $total->collect($address);

        // Both cards should be applied (100 + 50 = 150)
        expect((float) $address->getBaseGiftcardAmount())->toBe(150.00);
    });

    test('caps multiple gift cards at order total', function () {
        // Create second gift card - total would exceed order
        $card2 = Mage::getModel('giftcard/giftcard');
        $card2->setCode($this->helper->generateCode());
        $card2->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $card2->setWebsiteId(1);
        $card2->setBalance(100.00);
        $card2->setInitialBalance(100.00);
        $card2->save();

        $codes = [
            $this->giftcard->getCode() => 0, // 100
            $card2->getCode() => 0, // 100
        ];
        $this->quote->setGiftcardCodes(json_encode($codes));

        $address = $this->quote->getShippingAddress();
        $address->setBaseSubtotal(120.00); // Less than total cards
        $address->setSubtotal(120.00);
        $address->setBaseShippingInclTax(0);
        $address->setShippingInclTax(0);

        $item = createMockQuoteItem([
            'base_row_total_incl_tax' => 120.00,
            'row_total_incl_tax' => 120.00,
        ]);
        setAddressItems($address, [$item]);

        $total = Mage::getModel('giftcard/total_quote');
        $total->collect($address);

        // Should be capped at order total
        expect((float) $address->getBaseGiftcardAmount())->toBe(120.00);
    });

    test('excludes gift card products from gift card payment', function () {
        $codes = [$this->giftcard->getCode() => 0];
        $this->quote->setGiftcardCodes(json_encode($codes));

        $address = $this->quote->getShippingAddress();
        $address->setBaseShippingInclTax(0);
        $address->setShippingInclTax(0);

        // Mix of regular product and gift card product
        $regularItem = createMockQuoteItem([
            'product_type' => 'simple',
            'base_row_total_incl_tax' => 50.00,
            'row_total_incl_tax' => 50.00,
        ]);

        $giftcardItem = createMockQuoteItem([
            'product_type' => 'giftcard', // Gift card product
            'base_row_total_incl_tax' => 100.00,
            'row_total_incl_tax' => 100.00,
        ]);

        setAddressItems($address, [$regularItem, $giftcardItem]);

        $total = Mage::getModel('giftcard/total_quote');
        $total->collect($address);

        // Gift card should only apply to non-giftcard items (50.00)
        expect((float) $address->getBaseGiftcardAmount())->toBe(50.00);
    });

    test('removes invalid gift cards from codes', function () {
        // Create an invalid card
        $invalidCard = Mage::getModel('giftcard/giftcard');
        $invalidCard->setCode($this->helper->generateCode());
        $invalidCard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_DISABLED);
        $invalidCard->setWebsiteId(1);
        $invalidCard->setBalance(100.00);
        $invalidCard->setInitialBalance(100.00);
        $invalidCard->save();

        $codes = [
            $this->giftcard->getCode() => 0, // Valid
            $invalidCard->getCode() => 0, // Invalid
        ];
        $this->quote->setGiftcardCodes(json_encode($codes));

        $address = $this->quote->getShippingAddress();
        $address->setBaseSubtotal(200.00);
        $address->setSubtotal(200.00);
        $address->setBaseShippingInclTax(0);
        $address->setShippingInclTax(0);

        $item = createMockQuoteItem([
            'base_row_total_incl_tax' => 200.00,
            'row_total_incl_tax' => 200.00,
        ]);
        setAddressItems($address, [$item]);

        $total = Mage::getModel('giftcard/total_quote');
        $total->collect($address);

        // Only valid card should be applied
        expect((float) $address->getBaseGiftcardAmount())->toBe(100.00);

        // Check that invalid code was removed
        $updatedCodes = json_decode($this->quote->getGiftcardCodes(), true);
        expect($updatedCodes)->toHaveKey($this->giftcard->getCode());
        expect($updatedCodes)->not->toHaveKey($invalidCard->getCode());
    });

    test('skips billing address for non-virtual quotes', function () {
        $codes = [$this->giftcard->getCode() => 0];
        $this->quote->setGiftcardCodes(json_encode($codes));
        $this->quote->setIsVirtual(false);

        $billingAddress = $this->quote->getBillingAddress();
        $billingAddress->setAddressType('billing');
        $billingAddress->setBaseSubtotal(100.00);
        $billingAddress->setSubtotal(100.00);

        $total = Mage::getModel('giftcard/total_quote');
        $total->collect($billingAddress);

        // Should not apply to billing for non-virtual
        expect((float) $billingAddress->getBaseGiftcardAmount())->toBe(0.0);
    });

    test('handles empty gift card codes gracefully', function () {
        $this->quote->setGiftcardCodes(null);

        $address = $this->quote->getShippingAddress();
        $address->setBaseSubtotal(100.00);
        $address->setSubtotal(100.00);

        $total = Mage::getModel('giftcard/total_quote');
        $result = $total->collect($address);

        expect($result)->toBeInstanceOf(Maho_Giftcard_Model_Total_Quote::class);
        expect((float) $address->getBaseGiftcardAmount())->toBe(0.0);
    });

    test('handles empty codes array gracefully', function () {
        $this->quote->setGiftcardCodes('[]');

        $address = $this->quote->getShippingAddress();

        $total = Mage::getModel('giftcard/total_quote');
        $result = $total->collect($address);

        expect($result)->toBeInstanceOf(Maho_Giftcard_Model_Total_Quote::class);
    });
});

describe('Quote Total Fetch Method', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');

        $this->giftcard = Mage::getModel('giftcard/giftcard');
        $this->giftcard->setCode($this->helper->generateCode());
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setWebsiteId(1);
        $this->giftcard->setBalance(100.00);
        $this->giftcard->setInitialBalance(100.00);
        $this->giftcard->save();

        $this->quote = Mage::getModel('sales/quote');
        $this->quote->setStoreId(1);
        $this->quote->save();
    });

    test('fetch adds total to address when gift card applied', function () {
        $codes = [$this->giftcard->getCode() => 50.00];
        $this->quote->setGiftcardCodes(json_encode($codes));

        $address = $this->quote->getShippingAddress();
        $address->setGiftcardAmount(50.00);
        $address->setGiftcardCodes(json_encode($codes));

        $total = Mage::getModel('giftcard/total_quote');
        $total->fetch($address);

        $totals = $address->getTotals();
        expect($totals)->toHaveKey('giftcard');
        expect($totals['giftcard']->getValue())->toBe(50.00);
    });

    test('fetch includes gift card codes in total', function () {
        $codes = [$this->giftcard->getCode() => 75.00];
        $this->quote->setGiftcardCodes(json_encode($codes));

        $address = $this->quote->getShippingAddress();
        $address->setGiftcardAmount(75.00);
        $address->setGiftcardCodes(json_encode($codes));

        $total = Mage::getModel('giftcard/total_quote');
        $total->fetch($address);

        $totals = $address->getTotals();
        expect($totals['giftcard']->getData('giftcard_codes'))->toBe($this->giftcard->getCode());
    });

    test('fetch does not add total when no gift card applied', function () {
        $address = $this->quote->getShippingAddress();
        $address->setGiftcardAmount(0);

        $total = Mage::getModel('giftcard/total_quote');
        $total->fetch($address);

        $totals = $address->getTotals();
        expect($totals)->not->toHaveKey('giftcard');
    });
});

describe('Quote Total Website Validation', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');

        // Create gift card for website 1
        $this->giftcard = Mage::getModel('giftcard/giftcard');
        $this->giftcard->setCode($this->helper->generateCode());
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setWebsiteId(1);
        $this->giftcard->setBalance(100.00);
        $this->giftcard->setInitialBalance(100.00);
        $this->giftcard->save();

        $this->quote = Mage::getModel('sales/quote');
        $this->quote->setStoreId(1);
        $this->quote->save();
    });

    test('validates gift card against quote website', function () {
        // Card is for website 1, quote store is 1 (website 1)
        $codes = [$this->giftcard->getCode() => 0];
        $this->quote->setGiftcardCodes(json_encode($codes));

        $address = $this->quote->getShippingAddress();
        $address->setBaseSubtotal(50.00);
        $address->setSubtotal(50.00);
        $address->setBaseShippingInclTax(0);
        $address->setShippingInclTax(0);

        $item = createMockQuoteItem([
            'base_row_total_incl_tax' => 50.00,
            'row_total_incl_tax' => 50.00,
        ]);
        setAddressItems($address, [$item]);

        $total = Mage::getModel('giftcard/total_quote');
        $total->collect($address);

        // Should apply
        expect((float) $address->getBaseGiftcardAmount())->toBe(50.00);
    });
});
