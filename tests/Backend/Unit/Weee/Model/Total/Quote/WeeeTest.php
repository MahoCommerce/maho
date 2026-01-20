<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Maho\DataObject;

uses(Tests\MahoBackendTestCase::class);

/**
 * Mock helper that simulates WEEE being enabled with configurable settings
 */
class WeeeTest_MockHelper extends Mage_Weee_Helper_Data
{
    public bool $enabled = true;
    public bool $taxable = false;
    public bool $taxIncluded = false;
    public bool $includeInSubtotal = true;
    public array $weeeAttributes = [];

    #[\Override]
    public function isEnabled($store = null): bool
    {
        return $this->enabled;
    }

    #[\Override]
    public function isTaxable($store = null): bool
    {
        return $this->taxable;
    }

    #[\Override]
    public function isTaxIncluded($store = null): bool
    {
        return $this->taxIncluded;
    }

    #[\Override]
    public function includeInSubtotal($store = null): bool
    {
        return $this->includeInSubtotal;
    }

    #[\Override]
    public function getProductWeeeAttributes($product, $shipping = null, $billing = null, $website = null, $calculateTax = null, bool $round = true): array
    {
        return $this->weeeAttributes;
    }

    #[\Override]
    public function setStore($store): self
    {
        return $this;
    }

    #[\Override]
    public function setApplied($item, $value): self
    {
        $item->setWeeeTaxApplied(serialize($value));
        return $this;
    }

    #[\Override]
    public function getApplied($item): array
    {
        $applied = $item->getWeeeTaxApplied();
        return $applied ? unserialize($applied) : [];
    }
}

/**
 * Create a mock WEEE attribute
 */
function createWeeeAttribute(float $amount, string $code = 'FPT', string $name = 'Fixed Product Tax'): DataObject
{
    return new DataObject([
        'amount' => $amount,
        'code' => $code,
        'name' => $name,
    ]);
}

/**
 * Create a mock quote item for WEEE testing
 */
function createWeeeQuoteItem(array $data = []): DataObject
{
    $product = new DataObject([
        'id' => 1,
        'tax_class_id' => 2,
    ]);

    return new DataObject(array_merge([
        'parent_item_id' => null,
        'product' => $product,
        'total_qty' => 1,
        'has_children' => false,
    ], $data));
}

/**
 * Set mock items on an address for WEEE testing
 */
function setWeeeAddressItems(Mage_Sales_Model_Quote_Address $address, array $items): void
{
    $address->setData('cached_items_all', $items);
    $address->setData('cached_items_nonnominal', $items);
    $address->setData('cached_items_nominal', []);
}

describe('Weee Total Collector Instantiation', function () {
    test('total collector can be instantiated', function () {
        $total = Mage::getModel('weee/total_quote_weee');
        expect($total)->toBeInstanceOf(Mage_Weee_Model_Total_Quote_Weee::class);
    });

    test('has correct code', function () {
        $total = Mage::getModel('weee/total_quote_weee');
        expect($total->getCode())->toBe('weee');
    });
});

describe('Weee _processTotalAmount is always called', function () {
    beforeEach(function () {
        $this->quote = Mage::getModel('sales/quote');
        $this->quote->setStoreId(1);
        $this->quote->save();

        $this->mockHelper = new WeeeTest_MockHelper();
        $this->mockHelper->enabled = true;
    });

    test('processTotalAmount adds to subtotal when includeInSubtotal is true and has WEEE value', function () {
        // Setup: includeInSubtotal = true, WEEE amount = 10.00
        $this->mockHelper->includeInSubtotal = true;
        $this->mockHelper->weeeAttributes = [
            createWeeeAttribute(10.00),
        ];

        $address = $this->quote->getShippingAddress();

        $item = createWeeeQuoteItem(['total_qty' => 2]);
        setWeeeAddressItems($address, [$item]);

        $total = Mage::getModel('weee/total_quote_weee');
        $total->setHelper($this->mockHelper);
        $total->setStore(Mage::app()->getStore());
        $total->collect($address);

        // WEEE row value = 10.00 * 2 qty = 20.00
        // Subtotal total amount should be increased by 20.00
        expect((float) $address->getTotalAmount('subtotal'))->toBe(20.00);
        expect((float) $address->getBaseTotalAmount('subtotal'))->toBe(20.00);
    });

    test('processTotalAmount adds to extra tax when includeInSubtotal is false and has WEEE value', function () {
        // Setup: includeInSubtotal = false, WEEE amount = 10.00
        $this->mockHelper->includeInSubtotal = false;
        $this->mockHelper->weeeAttributes = [
            createWeeeAttribute(10.00),
        ];

        $address = $this->quote->getShippingAddress();
        $address->setExtraTaxAmount(0);
        $address->setBaseExtraTaxAmount(0);

        $item = createWeeeQuoteItem(['total_qty' => 2]);
        setWeeeAddressItems($address, [$item]);

        $total = Mage::getModel('weee/total_quote_weee');
        $total->setHelper($this->mockHelper);
        $total->setStore(Mage::app()->getStore());
        $total->collect($address);

        // WEEE row value = 10.00 * 2 qty = 20.00
        // Extra tax amount should be increased by 20.00
        // Subtotal total amount should remain 0
        expect((float) $address->getTotalAmount('subtotal'))->toBe(0.0);
        expect((float) $address->getExtraTaxAmount())->toBe(20.00);
        expect((float) $address->getBaseExtraTaxAmount())->toBe(20.00);
    });

    test('subtotal is correctly updated even when WEEE has row value - regression test for short-circuit bug', function () {
        // This is the specific regression test for the bug fix
        // The bug was: when hasRowValue was true, _processTotalAmount was skipped due to short-circuit evaluation
        // This meant the subtotal was NOT updated when includeInSubtotal was true

        $this->mockHelper->includeInSubtotal = true;
        $this->mockHelper->weeeAttributes = [
            createWeeeAttribute(15.00), // Non-zero value to trigger hasRowValue = true
        ];

        $address = $this->quote->getShippingAddress();

        $item = createWeeeQuoteItem(['total_qty' => 3]);
        setWeeeAddressItems($address, [$item]);

        $total = Mage::getModel('weee/total_quote_weee');
        $total->setHelper($this->mockHelper);
        $total->setStore(Mage::app()->getStore());
        $total->collect($address);

        // WEEE row value = 15.00 * 3 qty = 45.00
        // With the bug: subtotal total amount would be 0 (processTotalAmount skipped)
        // With the fix: subtotal total amount should be 45.00
        expect((float) $address->getTotalAmount('subtotal'))->toBe(45.00);
        expect((float) $address->getBaseTotalAmount('subtotal'))->toBe(45.00);
    });

    test('extra tax amount is correctly updated even when WEEE has row value - regression test', function () {
        // Same regression test but for the extra tax amount path

        $this->mockHelper->includeInSubtotal = false;
        $this->mockHelper->weeeAttributes = [
            createWeeeAttribute(15.00),
        ];

        $address = $this->quote->getShippingAddress();
        $address->setExtraTaxAmount(5.00); // Pre-existing value
        $address->setBaseExtraTaxAmount(5.00);

        $item = createWeeeQuoteItem(['total_qty' => 3]);
        setWeeeAddressItems($address, [$item]);

        $total = Mage::getModel('weee/total_quote_weee');
        $total->setHelper($this->mockHelper);
        $total->setStore(Mage::app()->getStore());
        $total->collect($address);

        // WEEE row value = 15.00 * 3 qty = 45.00
        // Extra tax should be: 5.00 + 45.00 = 50.00
        expect((float) $address->getExtraTaxAmount())->toBe(50.00);
        expect((float) $address->getBaseExtraTaxAmount())->toBe(50.00);
        // Subtotal total amount should remain 0
        expect((float) $address->getTotalAmount('subtotal'))->toBe(0.0);
    });
});

describe('Weee isTaxAffected flag behavior', function () {
    beforeEach(function () {
        $this->quote = Mage::getModel('sales/quote');
        $this->quote->setStoreId(1);
        $this->quote->save();

        $this->mockHelper = new WeeeTest_MockHelper();
        $this->mockHelper->enabled = true;
    });

    test('subtotal incl tax is unset when WEEE affects tax', function () {
        $this->mockHelper->includeInSubtotal = true;
        $this->mockHelper->weeeAttributes = [
            createWeeeAttribute(10.00),
        ];

        $address = $this->quote->getShippingAddress();
        $address->setSubtotalInclTax(110.00);
        $address->setBaseSubtotalInclTax(110.00);

        $item = createWeeeQuoteItem();
        setWeeeAddressItems($address, [$item]);

        $total = Mage::getModel('weee/total_quote_weee');
        $total->setHelper($this->mockHelper);
        $total->setStore(Mage::app()->getStore());
        $total->collect($address);

        // When tax is affected, subtotal incl tax should be unset
        expect($address->getSubtotalInclTax())->toBeNull();
        expect($address->getBaseSubtotalInclTax())->toBeNull();
    });

    test('subtotal incl tax is preserved when WEEE is disabled', function () {
        $this->mockHelper->enabled = false; // WEEE disabled
        $this->mockHelper->weeeAttributes = [
            createWeeeAttribute(10.00), // Would apply if enabled
        ];

        $address = $this->quote->getShippingAddress();
        $address->setSubtotalInclTax(110.00);
        $address->setBaseSubtotalInclTax(110.00);

        $item = createWeeeQuoteItem();
        setWeeeAddressItems($address, [$item]);

        $total = Mage::getModel('weee/total_quote_weee');
        $total->setHelper($this->mockHelper);
        $total->setStore(Mage::app()->getStore());
        $total->collect($address);

        // When WEEE is disabled, subtotal incl tax should be preserved
        expect((float) $address->getSubtotalInclTax())->toBe(110.00);
        expect((float) $address->getBaseSubtotalInclTax())->toBe(110.00);
    });
});

describe('Weee disabled scenarios', function () {
    beforeEach(function () {
        $this->quote = Mage::getModel('sales/quote');
        $this->quote->setStoreId(1);
        $this->quote->save();

        $this->mockHelper = new WeeeTest_MockHelper();
    });

    test('no changes when WEEE is disabled', function () {
        $this->mockHelper->enabled = false;
        $this->mockHelper->weeeAttributes = [
            createWeeeAttribute(10.00),
        ];

        $address = $this->quote->getShippingAddress();

        $item = createWeeeQuoteItem();
        setWeeeAddressItems($address, [$item]);

        $total = Mage::getModel('weee/total_quote_weee');
        $total->setHelper($this->mockHelper);
        $total->setStore(Mage::app()->getStore());
        $total->collect($address);

        // Subtotal total amount should remain 0 when WEEE is disabled
        expect((float) $address->getTotalAmount('subtotal'))->toBe(0.0);
    });

    test('returns early when no items in address', function () {
        $this->mockHelper->enabled = true;

        $address = $this->quote->getShippingAddress();
        setWeeeAddressItems($address, []); // No items

        $total = Mage::getModel('weee/total_quote_weee');
        $total->setHelper($this->mockHelper);
        $total->setStore(Mage::app()->getStore());
        $result = $total->collect($address);

        expect($result)->toBeInstanceOf(Mage_Weee_Model_Total_Quote_Weee::class);
        expect((float) $address->getTotalAmount('subtotal'))->toBe(0.0);
    });
});

describe('Weee with multiple items', function () {
    beforeEach(function () {
        $this->quote = Mage::getModel('sales/quote');
        $this->quote->setStoreId(1);
        $this->quote->save();

        $this->mockHelper = new WeeeTest_MockHelper();
        $this->mockHelper->enabled = true;
    });

    test('accumulates WEEE from multiple items', function () {
        $this->mockHelper->includeInSubtotal = true;
        $this->mockHelper->weeeAttributes = [
            createWeeeAttribute(5.00),
        ];

        $address = $this->quote->getShippingAddress();

        $item1 = createWeeeQuoteItem(['total_qty' => 2]); // 5 * 2 = 10
        $item2 = createWeeeQuoteItem(['total_qty' => 3]); // 5 * 3 = 15
        setWeeeAddressItems($address, [$item1, $item2]);

        $total = Mage::getModel('weee/total_quote_weee');
        $total->setHelper($this->mockHelper);
        $total->setStore(Mage::app()->getStore());
        $total->collect($address);

        // Total WEEE = 10 + 15 = 25
        expect((float) $address->getTotalAmount('subtotal'))->toBe(25.00);
    });
});
