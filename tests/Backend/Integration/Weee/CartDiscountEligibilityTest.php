<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

use Tests\Helpers\WeeeDiscountFixture;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $this->connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->connection->beginTransaction();
    $store = setStoreDisplayCurrency('USD', 'USD');
    $this->storeId = (int) $store->getId();
    $this->websiteId = (int) $store->getWebsiteId();
    Mage::app()->setCurrentStore($this->storeId);
    $store->setConfig(Mage_Weee_Helper_Data::XML_PATH_FPT_ENABLED, '1');
    $store->setConfig('tax/weee/discount', '1');
    $store->setConfig('tax/weee/apply_vat', '0');
    $store->setConfig('tax/weee/include_in_subtotal', '0');
    $store->setConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_DEFAULT_COUNTRY, 'US');
    $store->setConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_PRICE_INCLUDES_TAX, '0');
    $store->setConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_APPLY_AFTER_DISCOUNT, '1');

    $this->feeAttribute = 'discount_fee_' . uniqid();
    WeeeDiscountFixture::createFeeAttribute($this->feeAttribute);
    $this->groupId = (int) Mage::getModel('customer/group')
        ->setCode('Cart fee rule ' . uniqid())
        ->setTaxClassId((int) Mage::getModel('customer/group')->getTaxClassId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID))
        ->save()
        ->getId();

    $this->cart = Mage::getService('checkout/cart');
    $this->quote = $this->cart->create(null, $this->storeId)->setCustomerGroupId($this->groupId);
    $address = ['country_id' => 'US', 'region_id' => 12, 'postcode' => '90210'];
    $this->quote->getBillingAddress()->addData($address);
    $this->quote->getShippingAddress()->addData($address);
});

afterEach(function (): void {
    $this->connection->rollBack();
    resetCurrencyState();
});

function cartFeeEligibilityRule(
    int $websiteId,
    int $groupId,
    float $amount,
    int $priority,
    string $action = Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION,
    ?string $sku = null,
    ?string $country = null,
    ?float $maximumQty = null,
    ?int $step = null,
    bool $stop = false,
): void {
    $rule = Mage::getModel('salesrule/rule')
        ->setName('Cart fee rule ' . uniqid())
        ->setIsActive(true)
        ->setWebsiteIds([$websiteId])
        ->setCustomerGroupIds([$groupId])
        ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON)
        ->setSimpleAction($action)
        ->setDiscountAmount($amount)
        ->setDiscountQty($maximumQty)
        ->setDiscountStep($step)
        ->setSortOrder($priority)
        ->setStopRulesProcessing($stop);
    if ($sku !== null) {
        $rule->loadPost(['actions' => [
            '1' => ['type' => 'salesrule/rule_condition_product_combine', 'aggregator' => 'all', 'value' => '1'],
            '1--1' => ['type' => 'salesrule/rule_condition_product', 'attribute' => 'sku', 'operator' => '==', 'value' => $sku],
        ]]);
    }
    if ($country !== null) {
        $rule->loadPost(['conditions' => [
            '1' => ['type' => 'salesrule/rule_condition_combine', 'aggregator' => 'all', 'value' => '1'],
            '1--1' => ['type' => 'salesrule/rule_condition_address', 'attribute' => 'country_id', 'operator' => '==', 'value' => $country],
        ]]);
    }
    $rule->save();
}

function addCartFeeEligibilityProduct(
    Mage_Sales_Model_Quote $quote,
    string $feeAttribute,
    float $qty,
): Mage_Sales_Model_Quote_Item {
    $store = $quote->getStore();
    $fixture = WeeeDiscountFixture::createProduct(
        'eligible-fee-' . uniqid(),
        $feeAttribute,
        [(int) $store->getWebsiteId()],
        price: 100.0,
    );
    $product = Mage::getModel('catalog/product')->setStoreId((int) $store->getId())->load($fixture['product']->getId());
    Mage::getService('checkout/cart')->addProduct($quote, $product, new Maho\DataObject(['qty' => $qty]), save: false);
    return $quote->getItemByProduct($product);
}

it('discounts the fixed product tax only for items matching the rule and the address', function (): void {
    $eligible = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, 1.0);
    $other = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, 1.0);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 20.0, 0, sku: (string) $eligible->getSku());
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 50.0, 1, country: 'CA');

    $this->cart->collectAndSave($this->quote);

    expect($eligible->getDiscountAmount())->toEqualWithDelta(20.0, 0.0001)
        ->and($eligible->getWeeeDiscount())->toEqualWithDelta(2.0, 0.0001)
        ->and($eligible->getBaseWeeeDiscount())->toEqualWithDelta(2.0, 0.0001)
        ->and($other->getDiscountAmount())->toEqualWithDelta(0.0, 0.0001)
        ->and($other->getWeeeDiscount())->toEqualWithDelta(0.0, 0.0001)
        ->and($this->quote->getGrandTotal())->toEqualWithDelta(198.0, 0.0001);
});

it('stops discounting the fixed product tax after a fixed item rule stops later rules', function (): void {
    $item = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, 1.0);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 5.0, 0, Mage_SalesRule_Model_Rule::BY_FIXED_ACTION, stop: true);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 50.0, 1);

    $this->cart->collectAndSave($this->quote);

    expect($item->getDiscountAmount())->toEqualWithDelta(5.0, 0.0001)
        ->and($item->getWeeeDiscount())->toEqualWithDelta(0.0, 0.0001)
        ->and($this->quote->getGrandTotal())->toEqualWithDelta(105.0, 0.0001);
});

it('stacks the eligible percentage rules on the remaining fixed product tax', function (bool $includeInSubtotal): void {
    $this->quote->getStore()->setConfig('tax/weee/include_in_subtotal', (string) (int) $includeInSubtotal);
    $item = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, 1.0);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 10.0, 0);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 20.0, 1, stop: true);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 90.0, 2);

    for ($collection = 0; $collection < 2; $collection++) {
        $this->cart->collectAndSave($this->quote);

        expect($item->getDiscountAmount())->toEqualWithDelta($includeInSubtotal ? 30.8 : 28.0, 0.0001)
            ->and($item->getWeeeDiscount())->toEqualWithDelta(2.8, 0.0001)
            ->and($item->getBaseWeeeDiscount())->toEqualWithDelta(2.8, 0.0001)
            ->and($this->quote->getGrandTotal())->toEqualWithDelta(79.2, 0.0001);
    }
})->with([
    'fee outside the subtotal' => [false],
    'fee included in the subtotal' => [true],
]);

it('applies the quantity limit and step before discounting the fixed product tax', function (float $qty, float $discount, float $total): void {
    $item = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, $qty);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 50.0, 0, maximumQty: 3.0, step: 2);

    $this->cart->collectAndSave($this->quote);

    expect($item->getDiscountAmount())->toEqualWithDelta($discount * 10, 0.0001)
        ->and($item->getWeeeDiscount())->toEqualWithDelta($discount, 0.0001)
        ->and($item->getBaseWeeeDiscount())->toEqualWithDelta($discount, 0.0001)
        ->and($this->quote->getGrandTotal())->toEqualWithDelta($total, 0.0001);
})->with([
    'one full step within the maximum' => [5.0, 10.0, 440.0],
    'no full step' => [1.0, 0.0, 110.0],
]);

it('compounds a percentage capped to part of the line with a full-line discount', function (): void {
    $item = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, 5.0);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 50.0, 0, maximumQty: 3.0, step: 2);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 20.0, 1);

    $this->cart->collectAndSave($this->quote);

    expect($item->getDiscountAmount())->toEqualWithDelta(180.0, 0.0001)
        ->and($item->getWeeeDiscount())->toEqualWithDelta(18.0, 0.0001)
        ->and($item->getBaseWeeeDiscount())->toEqualWithDelta(18.0, 0.0001)
        ->and($this->quote->getGrandTotal())->toEqualWithDelta(352.0, 0.0001);
});
