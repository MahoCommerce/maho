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

function validatorDiscountRule(
    int $groupId,
    string $action = Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION,
    float $amount = 10.0,
    int $sortOrder = 0,
    ?string $couponCode = null,
): void {
    Mage::getModel('salesrule/rule')
        ->setName('Discount test ' . uniqid())
        ->setIsActive()
        ->setWebsiteIds([1])
        ->setCustomerGroupIds([$groupId])
        ->setCouponType($couponCode === null ? Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON : Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
        ->setCouponCode($couponCode)
        ->setSimpleAction($action)
        ->setDiscountAmount($amount)
        ->setSortOrder($sortOrder)
        ->setStopRulesProcessing(false)
        ->save();
}

function validatorDiscountProduct(
    float $price,
    Mage_Core_Model_Store $store,
    string $type = Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL,
): Mage_Catalog_Model_Product {
    $product = Mage::getModel('catalog/product')->setStoreId(0);
    $product->setAttributeSetId($product->getDefaultAttributeSetId())
        ->setTypeId($type)
        ->setSku('discount-rounding-' . uniqid())
        ->setName('Discount rounding test item')
        ->setPrice($price)
        ->setWeight(1.0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)
        ->setWebsiteIds([(int) $store->getWebsiteId()])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100])
        ->save();

    return Mage::getModel('catalog/product')->setStoreId($store->getId())->load($product->getId());
}

it('keeps a percentage discount the same when the cart recalculates its totals', function (): void {
    $groupId = (int) $this->group->getId();
    validatorDiscountRule($groupId);
    $store = Mage::app()->getStore(1);
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setCustomerGroupId($groupId);
    $quote->addProduct(validatorDiscountProduct(0.15, $store), 1);
    $quote->getBillingAddress();
    $item = $quote->getAllVisibleItems()[0];

    $quote->collectTotals();
    expect($item->getDiscountAmount())->toBe(0.02)
        ->and($item->getBaseDiscountAmount())->toBe(0.02);

    $quote->setTotalsCollectedFlag(false)->collectTotals();
    expect($item->getDiscountAmount())->toBe(0.02)
        ->and($item->getBaseDiscountAmount())->toBe(0.02);
});

it('keeps the same cart discount and total after a storefront currency change', function (): void {
    $groupId = (int) $this->group->getId();
    validatorDiscountRule($groupId);
    Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => 0.72]]);
    $store = setStoreDisplayCurrency('USD', 'USD,EUR');
    $storeId = (int) $store->getId();
    $cart = Mage::getService('checkout/cart');
    $quote = $cart->create(null, $storeId)->setCustomerGroupId($groupId);
    $cart->addProduct($quote, validatorDiscountProduct(0.22, $store), new Maho\DataObject(['qty' => 1]), save: false);
    $cart->collectAndSave($quote);

    expect($quote->getQuoteCurrencyCode())->toBe('USD')
        ->and($quote->getGrandTotal())->toEqualWithDelta(0.20, 0.0001);

    $store->setCurrentCurrencyCode('EUR');
    $reloaded = Mage::getModel('sales/quote')->setStoreId($storeId)->load($quote->getId());
    foreach ([$quote, $reloaded] as $currentQuote) {
        $cart->collectAndSave($currentQuote);
        $item = $currentQuote->getAllVisibleItems()[0];
        expect($currentQuote->getQuoteCurrencyCode())->toBe('EUR')
            ->and([
                'discount' => $item->getDiscountAmount(),
                'base discount' => $item->getBaseDiscountAmount(),
                'total' => round((float) $currentQuote->getGrandTotal(), 2),
                'base total' => round((float) $currentQuote->getBaseGrandTotal(), 2),
            ])->toBe([
                'discount' => 0.02,
                'base discount' => 0.02,
                'total' => 0.14,
                'base total' => 0.20,
            ]);
    }
});

it('carries rounding between lines during each totals collection', function (): void {
    $groupId = (int) $this->group->getId();
    validatorDiscountRule($groupId);
    $store = Mage::app()->getStore(1);
    $cart = Mage::getService('checkout/cart');
    $quote = $cart->create(null, (int) $store->getId())->setCustomerGroupId($groupId);
    for ($line = 0; $line < 3; $line++) {
        $product = validatorDiscountProduct(0.12, $store);
        $cart->addProduct($quote, $product, new Maho\DataObject(['qty' => 1]), save: false);
    }

    for ($pass = 0; $pass < 2; $pass++) {
        $cart->collectAndSave($quote);
        $discounts = array_map(
            static fn(Mage_Sales_Model_Quote_Item $item): array => [$item->getDiscountAmount(), $item->getBaseDiscountAmount()],
            $quote->getAllVisibleItems(),
        );
        sort($discounts);
        expect($discounts)->toBe([[0.01, 0.01], [0.01, 0.01], [0.02, 0.02]])
            ->and($quote->getBillingAddress()->getDiscountAmount())->toEqualWithDelta(-0.04, 0.0001)
            ->and($quote->getGrandTotal())->toEqualWithDelta(0.32, 0.0001);
    }
});

it('keeps stacked percentage and fixed cart discounts stable on a mixed admin order', function (): void {
    $groupId = (int) $this->group->getId();
    validatorDiscountRule($groupId);
    validatorDiscountRule($groupId, Mage_SalesRule_Model_Rule::CART_FIXED_ACTION, 0.03, 1);
    $store = Mage::app()->getStore(1);
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setCustomerGroupId($groupId);
    $billing = $quote->getBillingAddress();
    $shipping = $quote->getShippingAddress();
    foreach ([
        Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
        Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL,
        Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL,
    ] as $type) {
        $quote->addProduct(validatorDiscountProduct(0.12, $store, $type), 1);
    }
    $order = Mage::getModel('adminhtml/sales_order_create')->setQuote($quote);

    for ($pass = 0; $pass < 2; $pass++) {
        $quote->setTotalsCollectedFlag(false);
        $order->collectRates();

        expect($billing->getAllItems())->toBeEmpty()
            ->and($shipping->getAllItems())->toHaveCount(3)
            ->and($shipping->getDiscountAmount())->toEqualWithDelta(-0.07, 0.0001)
            ->and($shipping->getBaseDiscountAmount())->toEqualWithDelta(-0.07, 0.0001)
            ->and($quote->getGrandTotal())->toEqualWithDelta(0.29, 0.0001);
    }
});

it('restores the automatic discount after removing a coupon', function (): void {
    $groupId = (int) $this->group->getId();
    $couponCode = 'discount-test-' . uniqid();
    validatorDiscountRule($groupId);
    validatorDiscountRule($groupId, sortOrder: 1, couponCode: $couponCode);
    $store = Mage::app()->getStore(1);
    $cart = Mage::getService('checkout/cart');
    $quote = $cart->create(null, (int) $store->getId())->setCustomerGroupId($groupId);
    $cart->addProduct($quote, validatorDiscountProduct(0.12, $store), new Maho\DataObject(['qty' => 1]), save: false);
    $cart->collectAndSave($quote);
    $item = $quote->getAllVisibleItems()[0];
    expect($item->getDiscountAmount())->toBe(0.01);

    $cart->applyCoupon($quote, $couponCode, Mage_Checkout_Model_Actor::Staff);
    expect($item->getDiscountAmount())->toBe(0.02);

    $cart->removeCoupon($quote);
    expect($item->getDiscountAmount())->toBe(0.01)
        ->and($item->getBaseDiscountAmount())->toBe(0.01)
        ->and($quote->getGrandTotal())->toEqualWithDelta(0.11, 0.0001);
});
