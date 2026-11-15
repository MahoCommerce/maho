<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
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
    public function setStore(Mage_Core_Model_Store $store): void {}

    #[\Override]
    public function setApplied(Maho\DataObject $item, array $value): static
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
}

describe('Weee Total Collector', function () {
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

    test('processTotalAmount adds the WEEE row value to the subtotal when includeInSubtotal is true', function (float $amount, int $qty, float $expected) {
        $this->mockHelper->includeInSubtotal = true;
        $this->mockHelper->weeeAttributes = [
            createWeeeAttribute($amount),
        ];

        $address = $this->quote->getShippingAddress();

        $item = createWeeeQuoteItem(['total_qty' => $qty]);
        setWeeeAddressItems($address, [$item]);

        $total = Mage::getModel('weee/total_quote_weee');
        $total->setHelper($this->mockHelper);
        $total->setStore(Mage::app()->getStore());
        $total->collect($address);

        expect((float) $address->getTotalAmount('subtotal'))->toBe($expected);
        expect((float) $address->getBaseTotalAmount('subtotal'))->toBe($expected);
    })->with([
        '10.00 x 2' => [10.00, 2, 20.00],
        '15.00 x 3' => [15.00, 3, 45.00],
    ]);

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
