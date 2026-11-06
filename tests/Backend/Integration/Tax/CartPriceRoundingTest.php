<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A catalog price without tax of 11.4754 and a rate of 22% show 14.00 on the product page, because
 * the page adds the tax to the full price (see PriceTemplateTest). The cart must charge the same.
 */

function cartPriceRoundingQuote(int $qty, float $price, int $productClassId): Mage_Sales_Model_Quote
{
    $product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addAttributeToSelect(['price', 'name'])
        ->setPageSize(1)
        ->getFirstItem();
    $product = Mage::getModel('catalog/product')->setStoreId(1)->load($product->getId());
    $product->setPrice($price)->setSpecialPrice(null)->setTierPrice([])->setTaxClassId($productClassId);

    $quote = Mage::getModel('sales/quote')->setStoreId(1);
    $quote->addProduct($product, $qty);
    $quote->getShippingAddress()->setCountryId('IT')->setPostcode('00100')->setCollectShippingRates();

    return $quote;
}

$taxConfigPaths = [
    'tax/calculation/price_includes_tax',
    'tax/calculation/algorithm',
    'tax/calculation/cross_border_trade_enabled',
    'tax/calculation/based_on',
    'tax/calculation/apply_after_discount',
];

beforeEach(function () use ($taxConfigPaths): void {
    $store = Mage::app()->getStore(1);
    $this->originalConfig = [];
    foreach ($taxConfigPaths as $path) {
        $this->originalConfig[$path] = $store->getConfig($path);
    }
    $store->setConfig('tax/calculation/price_includes_tax', '0');
    $store->setConfig('tax/calculation/cross_border_trade_enabled', '1');
    $store->setConfig('tax/calculation/based_on', 'shipping');

    $this->write = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->write->beginTransaction();
    $resource = Mage::getSingleton('core/resource');

    $productClassId = (int) $this->write->fetchOne(
        $this->write->select()->from($resource->getTableName('tax/tax_class'), 'class_id')->where('class_type = ?', 'PRODUCT')->limit(1),
    );
    $customerClassId = (int) Mage::getModel('customer/group')->getTaxClassId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);

    $this->write->insert($resource->getTableName('tax/tax_calculation_rate'), [
        'tax_country_id' => 'IT',
        'tax_region_id' => 0,
        'tax_postcode' => '*',
        'code' => 'IT-CART-ROUNDING-' . uniqid(),
        'rate' => 22,
    ]);
    $rateId = (int) $this->write->lastInsertId();
    $this->write->insert($resource->getTableName('tax/tax_calculation_rule'), [
        'code' => 'IT-CART-ROUNDING-' . uniqid(),
        'priority' => 0,
        'position' => 0,
    ]);
    $ruleId = (int) $this->write->lastInsertId();
    $this->write->insert($resource->getTableName('tax/tax_calculation'), [
        'tax_calculation_rate_id' => $rateId,
        'tax_calculation_rule_id' => $ruleId,
        'customer_tax_class_id' => $customerClassId,
        'product_tax_class_id' => $productClassId,
    ]);
    $this->productClassId = $productClassId;
});

afterEach(function (): void {
    $this->write->rollBack();
    $store = Mage::app()->getStore(1);
    foreach ($this->originalConfig as $path => $value) {
        $store->setConfig($path, $value);
    }
});

it('charges in the cart the price that the product page shows for a price without tax', function (string $algorithm): void {
    Mage::app()->getStore(1)->setConfig('tax/calculation/algorithm', $algorithm);

    $quote = cartPriceRoundingQuote(1, 11.4754, $this->productClassId);
    $quote->collectTotals();

    $item = $quote->getAllVisibleItems()[0];
    $address = $quote->getShippingAddress();

    expect(round((float) $item->getPriceInclTax(), 2))->toBe(14.0)
        ->and(round((float) $item->getRowTotalInclTax(), 2))->toBe(14.0)
        ->and(round((float) $address->getTaxAmount(), 2))->toBe(2.52)
        ->and(round((float) $address->getGrandTotal(), 2))->toBe(14.0)
        ->and(round((float) $address->getBaseTaxAmount(), 2))->toBe(2.52)
        ->and(round((float) $address->getBaseGrandTotal(), 2))->toBe(14.0);
})->with([
    'unit price' => [Mage_Tax_Model_Calculation::CALC_UNIT_BASE],
    'row total' => [Mage_Tax_Model_Calculation::CALC_ROW_BASE],
    'total' => [Mage_Tax_Model_Calculation::CALC_TOTAL_BASE],
]);

it('takes the tax of a line with more than one unit from the unrounded price', function (string $algorithm, float $expectedTax): void {
    Mage::app()->getStore(1)->setConfig('tax/calculation/algorithm', $algorithm);

    $quote = cartPriceRoundingQuote(2, 11.4754, $this->productClassId);
    $quote->collectTotals();

    expect(round((float) $quote->getShippingAddress()->getTaxAmount(), 2))->toBe($expectedTax);
})->with([
    'unit price' => [Mage_Tax_Model_Calculation::CALC_UNIT_BASE, 5.04],
    'row total' => [Mage_Tax_Model_Calculation::CALC_ROW_BASE, 5.05],
    'total' => [Mage_Tax_Model_Calculation::CALC_TOTAL_BASE, 5.05],
]);

it('adds no hidden tax when a discount takes the whole line', function (string $algorithm): void {
    Mage::app()->getStore(1)->setConfig('tax/calculation/algorithm', $algorithm);
    Mage::app()->getStore(1)->setConfig('tax/calculation/apply_after_discount', '1');

    $rule = Mage::getModel('salesrule/rule');
    $rule->setName('Cart rounding full discount ' . uniqid())
        ->setIsActive()
        ->setWebsiteIds([1])
        ->setCustomerGroupIds([0, 1, 2, 3])
        ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON)
        ->setSimpleAction('by_percent')
        ->setDiscountAmount(100)
        ->save();

    $quote = cartPriceRoundingQuote(10, 10.006, $this->productClassId);
    $quote->collectTotals();
    $address = $quote->getShippingAddress();

    expect(round((float) $address->getDiscountAmount(), 2))->toBe(-100.1)
        ->and(round((float) $address->getHiddenTaxAmount(), 2))->toBe(0.0)
        ->and(round((float) $address->getGrandTotal(), 2))->toBe(0.0);
})->with([
    'unit price' => [Mage_Tax_Model_Calculation::CALC_UNIT_BASE],
    'row total' => [Mage_Tax_Model_Calculation::CALC_ROW_BASE],
]);
