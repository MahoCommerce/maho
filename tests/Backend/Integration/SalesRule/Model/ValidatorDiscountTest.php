<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $this->connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->connection->beginTransaction();
    setStoreDisplayCurrency('USD', 'USD');
    $this->group = Mage::getModel('customer/group')
        ->setCode('Discount test ' . uniqid())
        ->setTaxClassId((int) Mage::getModel('customer/group')->getTaxClassId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID))
        ->save();
});

afterEach(function (): void {
    $this->connection->rollBack();
    resetCurrencyState();
});

function validatorDiscountRule(int $groupId, string $action, float $amount, int $sortOrder = 0): Mage_SalesRule_Model_Rule
{
    return Mage::getModel('salesrule/rule')
        ->setName('Discount test ' . uniqid())
        ->setIsActive()
        ->setWebsiteIds([1])
        ->setCustomerGroupIds([$groupId])
        ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON)
        ->setSimpleAction($action)
        ->setDiscountAmount($amount)
        ->setSortOrder($sortOrder)
        ->save();
}

function validatorDiscountQuote(int $groupId, float $price, int $qty = 1): Mage_Sales_Model_Quote
{
    $product = loadSimplePricedProduct()
        ->setPrice($price)
        ->setSpecialPrice(null)
        ->setTaxClassId(0);
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setCustomerGroupId($groupId);
    $quote->addProduct($product, $qty);
    $quote->getBillingAddress()->addData(['country_id' => 'US', 'region_id' => 12, 'postcode' => '90210']);
    $quote->getShippingAddress()->addData(['country_id' => 'US', 'region_id' => 12, 'postcode' => '90210']);
    return $quote;
}

it('keeps a percentage discount the same when the cart recalculates its totals', function (): void {
    $groupId = (int) $this->group->getId();
    validatorDiscountRule($groupId, Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION, 10.0);
    $quote = validatorDiscountQuote($groupId, 0.15);
    $item = $quote->getAllVisibleItems()[0];

    $quote->collectTotals();
    expect($item->getDiscountAmount())->toBe(0.02)
        ->and($item->getBaseDiscountAmount())->toBe(0.02);

    $quote->setTotalsCollectedFlag(false)->collectTotals();
    expect($item->getDiscountAmount())->toBe(0.02)
        ->and($item->getBaseDiscountAmount())->toBe(0.02);
});
