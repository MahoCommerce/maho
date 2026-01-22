<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

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

        // Find an existing simple product from sample data
        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToSelect(['price', 'name'])
            ->setPageSize(1);
        $this->product = $productCollection->getFirstItem();

        if (!$this->product->getId()) {
            $this->markTestSkipped('No simple product available for testing');
        }

        // Create a test gift card with balance of 100
        $this->giftcard = Mage::getModel('giftcard/giftcard');
        $this->giftcard->setCode($this->helper->generateCode());
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setWebsiteId(1);
        $this->giftcard->setBalance(100.00);
        $this->giftcard->setInitialBalance(100.00);
        $this->giftcard->save();

        // Create a fresh quote
        $this->quote = Mage::getModel('sales/quote');
        $this->quote->setStoreId(1);
    });

    test('applies gift card to quote with real product', function () {
        // Add the product to quote
        $this->quote->addProduct($this->product, 1);
        $this->quote->save();

        // Apply gift card code
        $codes = [$this->giftcard->getCode() => 0];
        $this->quote->setGiftcardCodes(json_encode($codes));

        // Set shipping address (required for non-virtual quotes)
        $shippingAddress = $this->quote->getShippingAddress();
        $shippingAddress->setCountryId('US');
        $shippingAddress->setRegionId(12); // California
        $shippingAddress->setPostcode('90210');
        $shippingAddress->setCollectShippingRates(true);

        // Collect totals - this runs all collectors in order
        $this->quote->collectTotals();

        $address = $this->quote->getShippingAddress();
        $productPrice = (float) $this->product->getPrice();

        // Gift card should be applied (min of card balance and total)
        $expectedGiftcardAmount = min(100.00, $productPrice);
        expect((float) $address->getBaseGiftcardAmount())->toBe($expectedGiftcardAmount);
    });

    test('applies partial gift card when balance less than total', function () {
        // Create a gift card with low balance
        $smallCard = Mage::getModel('giftcard/giftcard');
        $smallCard->setCode($this->helper->generateCode());
        $smallCard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $smallCard->setWebsiteId(1);
        $smallCard->setBalance(5.00); // Very small balance
        $smallCard->setInitialBalance(5.00);
        $smallCard->save();

        // Add product to quote
        $this->quote->addProduct($this->product, 1);
        $this->quote->save();

        $codes = [$smallCard->getCode() => 0];
        $this->quote->setGiftcardCodes(json_encode($codes));

        $shippingAddress = $this->quote->getShippingAddress();
        $shippingAddress->setCountryId('US');
        $shippingAddress->setRegionId(12);
        $shippingAddress->setPostcode('90210');
        $shippingAddress->setCollectShippingRates(true);

        $this->quote->collectTotals();

        $address = $this->quote->getShippingAddress();

        // Only card balance should be applied (assuming product costs more than $5)
        if ((float) $this->product->getPrice() > 5.00) {
            expect((float) $address->getBaseGiftcardAmount())->toBe(5.00);
        }
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

        // Add product to quote (multiple qty to ensure high total)
        $this->quote->addProduct($this->product, 10);
        $this->quote->save();

        $codes = [
            $this->giftcard->getCode() => 0,
            $card2->getCode() => 0,
        ];
        $this->quote->setGiftcardCodes(json_encode($codes));

        $shippingAddress = $this->quote->getShippingAddress();
        $shippingAddress->setCountryId('US');
        $shippingAddress->setRegionId(12);
        $shippingAddress->setPostcode('90210');
        $shippingAddress->setCollectShippingRates(true);

        $this->quote->collectTotals();

        $address = $this->quote->getShippingAddress();
        $totalPrice = (float) $this->product->getPrice() * 10;

        // Both cards should be applied (100 + 50 = 150) if total allows
        $expectedGiftcardAmount = min(150.00, $totalPrice);
        expect((float) $address->getBaseGiftcardAmount())->toBe($expectedGiftcardAmount);
    });

    test('caps gift cards at order total', function () {
        // Create a very large gift card
        $bigCard = Mage::getModel('giftcard/giftcard');
        $bigCard->setCode($this->helper->generateCode());
        $bigCard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $bigCard->setWebsiteId(1);
        $bigCard->setBalance(10000.00); // Much more than any product
        $bigCard->setInitialBalance(10000.00);
        $bigCard->save();

        // Add single product
        $this->quote->addProduct($this->product, 1);
        $this->quote->save();

        $codes = [$bigCard->getCode() => 0];
        $this->quote->setGiftcardCodes(json_encode($codes));

        $shippingAddress = $this->quote->getShippingAddress();
        $shippingAddress->setCountryId('US');
        $shippingAddress->setRegionId(12);
        $shippingAddress->setPostcode('90210');
        $shippingAddress->setCollectShippingRates(true);

        $this->quote->collectTotals();

        $address = $this->quote->getShippingAddress();
        $productPrice = (float) $this->product->getPrice();

        // Gift card should be capped at order total, not full $10000
        expect((float) $address->getBaseGiftcardAmount())->toBeLessThanOrEqual($productPrice + 50); // Allow for shipping
        expect((float) $address->getBaseGiftcardAmount())->toBeLessThan(10000.00);
    });

    test('removes invalid gift cards from codes during collection', function () {
        // Create an invalid (disabled) card
        $invalidCard = Mage::getModel('giftcard/giftcard');
        $invalidCard->setCode($this->helper->generateCode());
        $invalidCard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_DISABLED);
        $invalidCard->setWebsiteId(1);
        $invalidCard->setBalance(100.00);
        $invalidCard->setInitialBalance(100.00);
        $invalidCard->save();

        $this->quote->addProduct($this->product, 1);
        $this->quote->save();

        $codes = [
            $this->giftcard->getCode() => 0, // Valid
            $invalidCard->getCode() => 0, // Invalid
        ];
        $this->quote->setGiftcardCodes(json_encode($codes));

        $shippingAddress = $this->quote->getShippingAddress();
        $shippingAddress->setCountryId('US');
        $shippingAddress->setRegionId(12);
        $shippingAddress->setPostcode('90210');
        $shippingAddress->setCollectShippingRates(true);

        $this->quote->collectTotals();

        // Check that invalid code was removed from quote
        $updatedCodes = json_decode($this->quote->getGiftcardCodes(), true);
        expect($updatedCodes)->toHaveKey($this->giftcard->getCode());
        expect($updatedCodes)->not->toHaveKey($invalidCard->getCode());
    });

    test('handles empty gift card codes gracefully', function () {
        $this->quote->addProduct($this->product, 1);
        $this->quote->save();
        $this->quote->setGiftcardCodes(null);

        $shippingAddress = $this->quote->getShippingAddress();
        $shippingAddress->setCountryId('US');
        $shippingAddress->setRegionId(12);
        $shippingAddress->setPostcode('90210');
        $shippingAddress->setCollectShippingRates(true);

        $this->quote->collectTotals();

        $address = $this->quote->getShippingAddress();
        expect((float) $address->getBaseGiftcardAmount())->toBe(0.0);
    });

    test('handles empty codes array gracefully', function () {
        $this->quote->addProduct($this->product, 1);
        $this->quote->save();
        $this->quote->setGiftcardCodes('[]');

        $shippingAddress = $this->quote->getShippingAddress();
        $shippingAddress->setCountryId('US');
        $shippingAddress->setRegionId(12);
        $shippingAddress->setPostcode('90210');
        $shippingAddress->setCollectShippingRates(true);

        $this->quote->collectTotals();

        $address = $this->quote->getShippingAddress();
        expect((float) $address->getBaseGiftcardAmount())->toBe(0.0);
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

        // Find an existing simple product
        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToSelect(['price', 'name'])
            ->setPageSize(1);
        $this->product = $productCollection->getFirstItem();

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
    });

    test('validates gift card against quote website', function () {
        if (!$this->product->getId()) {
            $this->markTestSkipped('No simple product available for testing');
        }

        // Card is for website 1, quote store is 1 (website 1)
        $this->quote->addProduct($this->product, 1);
        $this->quote->save();

        $codes = [$this->giftcard->getCode() => 0];
        $this->quote->setGiftcardCodes(json_encode($codes));

        $shippingAddress = $this->quote->getShippingAddress();
        $shippingAddress->setCountryId('US');
        $shippingAddress->setRegionId(12);
        $shippingAddress->setPostcode('90210');
        $shippingAddress->setCollectShippingRates(true);

        $this->quote->collectTotals();

        $address = $this->quote->getShippingAddress();
        $productPrice = (float) $this->product->getPrice();

        // Should apply (website matches)
        $expectedAmount = min(100.00, $productPrice);
        expect((float) $address->getBaseGiftcardAmount())->toBe($expectedAmount);
    });
});

describe('Giftcard Product Exclusion', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');

        // Find an existing simple product
        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToSelect(['price', 'name'])
            ->setPageSize(1);
        $this->simpleProduct = $productCollection->getFirstItem();

        // Create a giftcard product for testing
        $this->giftcardProduct = Mage::getModel('catalog/product');
        $this->giftcardProduct->setTypeId('giftcard');
        $this->giftcardProduct->setAttributeSetId(
            Mage::getModel('catalog/product')->getDefaultAttributeSetId(),
        );
        $this->giftcardProduct->setWebsiteIds([1]);
        $this->giftcardProduct->setName('Test Gift Card Product');
        $this->giftcardProduct->setSku('test-giftcard-' . uniqid());
        $this->giftcardProduct->setStatus(1);
        $this->giftcardProduct->setVisibility(4);
        $this->giftcardProduct->setPrice(0);
        $this->giftcardProduct->setData('giftcard_type', 'fixed');
        $this->giftcardProduct->setData('giftcard_amounts', '25,50,100');
        $this->giftcardProduct->setData('giftcard_allow_message', 1);
        $this->giftcardProduct->setStockData([
            'use_config_manage_stock' => 0,
            'manage_stock' => 0,
            'is_in_stock' => 1,
            'qty' => 100,
        ]);
        $this->giftcardProduct->save();

        // Create a test gift card for payment (large balance)
        $this->giftcard = Mage::getModel('giftcard/giftcard');
        $this->giftcard->setCode($this->helper->generateCode());
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setWebsiteId(1);
        $this->giftcard->setBalance(500.00);
        $this->giftcard->setInitialBalance(500.00);
        $this->giftcard->save();

        $this->quote = Mage::getModel('sales/quote');
        $this->quote->setStoreId(1);
    });

    test('giftcard payment only applies to non-giftcard products in cart', function () {
        if (!$this->simpleProduct->getId()) {
            $this->markTestSkipped('No simple product available for testing');
        }

        // Add simple product to the quote
        $this->quote->addProduct($this->simpleProduct, 1);

        // Reload giftcard product to ensure stock item is loaded
        $giftcardProduct = Mage::getModel('catalog/product')->load($this->giftcardProduct->getId());

        // Add giftcard product with specific amount
        $buyRequest = new Maho\DataObject([
            'qty' => 1,
            'giftcard_amount' => 50.00,
        ]);
        $this->quote->addProduct($giftcardProduct, $buyRequest);
        $this->quote->save();

        // Apply gift card code for payment
        $codes = [$this->giftcard->getCode() => 0];
        $this->quote->setGiftcardCodes(json_encode($codes));

        $shippingAddress = $this->quote->getShippingAddress();
        $shippingAddress->setCountryId('US');
        $shippingAddress->setRegionId(12);
        $shippingAddress->setPostcode('90210');
        $shippingAddress->setCollectShippingRates(true);

        $this->quote->collectTotals();

        $address = $this->quote->getShippingAddress();
        $simpleProductPrice = (float) $this->simpleProduct->getPrice();

        // Gift card payment should only cover the simple product, not the giftcard product
        $giftcardAmount = (float) $address->getBaseGiftcardAmount();

        // The giftcard payment should be limited to the simple product price
        // (not including the $50 giftcard product which should be excluded)
        expect($giftcardAmount)->toBeLessThanOrEqual($simpleProductPrice + 50); // Allow for shipping
        expect($giftcardAmount)->toBeGreaterThan(0);
    });
});
