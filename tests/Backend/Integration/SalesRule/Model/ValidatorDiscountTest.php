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
    ?float $maxQty = null,
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
        ->setDiscountQty($maxQty)
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

function validatorDiscountObserve(string $eventName, Closure $observer, Closure $collect): void
{
    $app = Mage::app();
    $property = new ReflectionProperty($app, '_events');
    $originalEvents = $property->getValue($app);
    $events = $originalEvents;
    $alias = 'validator_discount_test/observer';
    $events['global'][$eventName] = ['observers' => [
        'discount_test' => ['type' => 'singleton', 'model' => $alias, 'method' => '__invoke'],
    ]];
    Mage::register('_singleton/' . $alias, $observer);
    $property->setValue($app, $events);
    try {
        $collect();
    } finally {
        $property->setValue($app, $originalEvents);
        Mage::unregister('_singleton/' . $alias);
    }
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

it('carries rounding between lines during each totals collection', function (
    float $price,
    float $percent,
    array $expectedDiscounts,
    float $expectedTotal,
): void {
    $groupId = (int) $this->group->getId();
    validatorDiscountRule($groupId, amount: $percent);
    $store = Mage::app()->getStore(1);
    $cart = Mage::getService('checkout/cart');
    $quote = $cart->create(null, (int) $store->getId())->setCustomerGroupId($groupId);
    foreach ($expectedDiscounts as $discount) {
        $product = validatorDiscountProduct($price, $store);
        $cart->addProduct($quote, $product, new Maho\DataObject(['qty' => 1]), save: false);
    }

    for ($pass = 0; $pass < 2; $pass++) {
        $cart->collectAndSave($quote);
        $discounts = array_map(
            static fn(Mage_Sales_Model_Quote_Item $item): array => [$item->getDiscountAmount(), $item->getBaseDiscountAmount()],
            $quote->getAllVisibleItems(),
        );
        sort($discounts);
        expect($discounts)->toBe($expectedDiscounts)
            ->and($quote->getBillingAddress()->getDiscountAmount())->toEqualWithDelta(-array_sum(array_column($expectedDiscounts, 0)), 0.0001)
            ->and($quote->getGrandTotal())->toEqualWithDelta($expectedTotal, 0.0001);
    }
})->with([
    'three lines' => [0.12, 10.0, [[0.01, 0.01], [0.01, 0.01], [0.02, 0.02]], 0.32],
    'just below half a cent' => [0.03, 24.9999, [[0.0, 0.0], [0.01, 0.01]], 0.05],
    'exactly half a cent' => [0.03, 25.0, [[0.01, 0.01], [0.01, 0.01]], 0.04],
    'just above half a cent' => [0.03, 25.0001, [[0.01, 0.01], [0.01, 0.01]], 0.04],
]);

it('carries a half-cent remainder past a free item without charging for it', function (
    string $currency,
    bool $hasThirdItem,
    array $expectedPaidDiscounts,
    float $expectedTotal,
): void {
    $groupId = (int) $this->group->getId();
    validatorDiscountRule($groupId, amount: 50.0);
    Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => 2.0]]);
    $store = setStoreDisplayCurrency($currency, 'USD,EUR');
    $storeId = (int) $store->getId();
    $cart = Mage::getService('checkout/cart');
    $quote = $cart->create(null, $storeId)->setCustomerGroupId($groupId);
    $freeProduct = validatorDiscountProduct(0.0, $store);
    $products = [validatorDiscountProduct(0.01, $store), $freeProduct];
    if ($hasThirdItem) {
        $products[] = validatorDiscountProduct(0.01, $store);
    }
    foreach ($products as $product) {
        $cart->addProduct($quote, $product, new Maho\DataObject(['qty' => 1]), save: false);
    }

    for ($pass = 0; $pass < 2; $pass++) {
        if ($pass > 0) {
            $quote = $cart->getById((int) $quote->getId());
        }
        $cart->collectAndSave($quote);
        $freeItem = $quote->getItemByProduct($freeProduct);
        $paidDiscounts = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($item !== $freeItem) {
                $paidDiscounts[] = [$item->getDiscountAmount(), $item->getBaseDiscountAmount()];
            }
        }
        sort($paidDiscounts);

        expect($quote->getGrandTotal())->toEqualWithDelta($expectedTotal, 0.0001)
            ->and($quote->getBaseGrandTotal())->toEqualWithDelta($hasThirdItem ? 0.01 : 0.0, 0.0001)
            ->and([$freeItem->getDiscountAmount(), $freeItem->getBaseDiscountAmount()])->toBe([0.0, 0.0])
            ->and($paidDiscounts)->toBe($expectedPaidDiscounts);
    }
})->with([
    'sale and base remainder' => ['USD', false, [[0.01, 0.01]], 0.0],
    'sale and base remainder reach the next paid item' => ['USD', true, [[0.0, 0.0], [0.01, 0.01]], 0.01],
    'base remainder only' => ['EUR', true, [[0.01, 0.0], [0.01, 0.01]], 0.02],
]);

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

it('lets a discount observer exclude the next line in a cart with a fixed discount', function (string $eventName): void {
    $groupId = (int) $this->group->getId();
    validatorDiscountRule($groupId, Mage_SalesRule_Model_Rule::CART_FIXED_ACTION, 6.0);
    validatorDiscountRule($groupId, Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION, 50.0, 1);
    $store = Mage::app()->getStore(1);
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setCustomerGroupId($groupId);
    $first = validatorDiscountProduct(10.0, $store);
    $second = validatorDiscountProduct(10.0, $store);
    $quote->addProduct($first, 1);
    $quote->addProduct($second, 1);
    $quote->getBillingAddress();

    $calls = [];
    $excluded = $quote->getItemByProduct($second);
    $observer = static function (Maho\Event\Observer $event) use (&$calls, $excluded): void {
        $calls[] = [(int) $event->getItem()->getProductId(), $event->getRule()?->getSimpleAction()];
        $excluded->setNoDiscount(true);
    };
    validatorDiscountObserve($eventName, $observer, $quote->collectTotals(...));

    $firstId = (int) $first->getId();
    expect($calls)->toBe($eventName === 'salesrule_validator_process'
        ? [[$firstId, Mage_SalesRule_Model_Rule::CART_FIXED_ACTION], [$firstId, Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION]]
        : [[$firstId, null]])
        ->and($quote->getItemByProduct($first)->getBaseDiscountAmount())->toBe(6.5)
        ->and($quote->getItemByProduct($second)->getBaseDiscountAmount())->toBe(0.0);
})->with(['salesrule_validator_process', 'sales_quote_address_discount_item']);

it('uses the same fixed cart allocation with either discount observer and counts only participating lines', function (
    string $eventName,
    bool $excludeSecond,
    array $expectedDiscounts,
    float $expectedTotal,
): void {
    $groupId = (int) $this->group->getId();
    $service = Mage::getService('salesrule/rule');
    foreach ([Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION, Mage_SalesRule_Model_Rule::CART_FIXED_ACTION] as $priority => $action) {
        $rule = Mage::getModel('salesrule/rule')->loadPost([
            'name' => 'Discount test ' . uniqid(),
            'is_active' => 1,
            'website_ids' => [1],
            'customer_group_ids' => [$groupId],
            'coupon_type' => Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON,
            'simple_action' => $action,
            'discount_amount' => $priority === 0 ? 100 : 6,
            'discount_qty' => $priority === 0 ? 1 : 0,
            'sort_order' => $priority,
            'stop_rules_processing' => $priority,
            'conditions' => [1 => ['type' => 'salesrule/rule_condition_combine', 'aggregator' => 'all', 'value' => '1']],
            'actions' => [1 => ['type' => 'salesrule/rule_condition_product_combine', 'aggregator' => 'any', 'value' => '0']],
        ]);
        $service->save($rule);
    }
    $store = Mage::app()->getStore(1);
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setCustomerGroupId($groupId);
    $quote->addProduct(validatorDiscountProduct(10.0, $store), 2);
    $second = validatorDiscountProduct(10.0, $store);
    $quote->addProduct($second, 1);
    $quote->getItemByProduct($second)->setNoDiscount($excludeSecond);
    $quote->getBillingAddress();

    $discounts = [];
    $observer = static function (Maho\Event\Observer $event) use (&$discounts): void {
        $discounts[] = $event->getResult()?->getBaseDiscountAmount() ?? $event->getItem()->getBaseDiscountAmount();
    };
    validatorDiscountObserve($eventName, $observer, $quote->collectTotals(...));
    expect($discounts)->toBe($expectedDiscounts)
        ->and($quote->getBaseGrandTotal())->toBe($expectedTotal);
})->with([
    'unrelated observer' => ['unrelated_discount_event', false, [], 4.0],
    'observer reads the proposed rule amounts' => ['salesrule_validator_process', false, [10.0, 6.0, 10.0, 0.0], 4.0],
    'observer reads both line discounts in order' => ['sales_quote_address_discount_item', false, [16.0, 10.0], 4.0],
    'second line excluded before collection' => ['sales_quote_address_discount_item', true, [16.0], 14.0],
]);

it('offers credit left by a discount observer to the next line', function (): void {
    $groupId = (int) $this->group->getId();
    validatorDiscountRule($groupId, Mage_SalesRule_Model_Rule::CART_FIXED_ACTION, 6.0);
    $store = Mage::app()->getStore(1);
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setCustomerGroupId($groupId);
    $quote->addProduct(validatorDiscountProduct(10.0, $store), 1);
    $quote->addProduct(validatorDiscountProduct(10.0, $store), 1);
    $quote->getBillingAddress();
    $proposals = [];
    validatorDiscountObserve('salesrule_validator_process', static function (Maho\Event\Observer $event) use (&$proposals): void {
        $result = $event->getResult();
        $proposals[] = $result->getBaseDiscountAmount();
        if (count($proposals) === 1) {
            $result->setBaseDiscountAmount(1.0)->setDiscountAmount(1.0);
        }
    }, $quote->collectTotals(...));
    expect($proposals)->toBe([3.0, 5.0])
        ->and($quote->getBaseGrandTotal())->toBe(14.0);
});

it('preserves the percentage rounding carry across a fixed cart rule in item order', function (
    float $firstCredit,
    float $expectedSecondPercentage,
    float $expectedBalance,
): void {
    $groupId = (int) $this->group->getId();
    validatorDiscountRule($groupId);
    validatorDiscountRule($groupId, Mage_SalesRule_Model_Rule::CART_FIXED_ACTION, 0.03, 1);
    validatorDiscountRule($groupId, sortOrder: 2, maxQty: 1);
    $store = Mage::app()->getStore(1);
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setCustomerGroupId($groupId);
    $first = validatorDiscountProduct(0.03, $store);
    $quote->addProduct($first, 1);
    $quote->addProduct(validatorDiscountProduct(0.01, $store), 1);
    $address = $quote->getBillingAddress();
    $discountBeforeCredit = [];
    validatorDiscountObserve('salesrule_validator_process', static function (Maho\Event\Observer $event) use (&$discountBeforeCredit, $firstCredit): void {
        if ($event->getRule()->getSimpleAction() === Mage_SalesRule_Model_Rule::CART_FIXED_ACTION) {
            $discountBeforeCredit[] = $event->getItem()->getBaseDiscountAmount();
            if (count($discountBeforeCredit) === 1) {
                $event->getResult()->setDiscountAmount($firstCredit)->setBaseDiscountAmount($firstCredit);
            }
        }
    }, $quote->collectTotals(...));
    expect($discountBeforeCredit)->toBe([0.0, $expectedSecondPercentage])
        ->and($quote->getItemByProduct($first)->getBaseDiscountAmount())->toBe($firstCredit)
        ->and(array_sum($address->getCartFixedRules()))->toEqualWithDelta($expectedBalance, 0.00001);
})->with([
    'proportional first credit makes the second line free' => [0.02, 0.01, 0.01],
    'full first credit leaves one cent payable on the second line before the fixed rule' => [0.03, 0.0, 0.0],
]);

it('excludes a child or its parent from a fixed cart discount on recollection', function (string $eventName): void {
    $groupId = (int) $this->group->getId();
    validatorDiscountRule($groupId, Mage_SalesRule_Model_Rule::CART_FIXED_ACTION, 6.01);
    $store = Mage::app()->getStore(1);
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setCustomerGroupId($groupId);
    $address = $quote->getBillingAddress();
    $parent = Mage::getModel('sales/quote_item')->setQuote($quote)
        ->setProduct(validatorDiscountProduct(0.0, $store))->setQty(1);
    $parent->getProduct()->setPriceType(Mage_Catalog_Model_Product_Type_Abstract::CALCULATE_CHILD);
    $children = [];
    for ($i = 0; $i < 3; $i++) {
        $children[] = Mage::getModel('sales/quote_item')->setQuote($quote)
            ->setProduct(validatorDiscountProduct(10.0, $store))->setQty(1)->setParentItem($parent)
            ->setDiscountCalculationPrice(10.0)->setBaseDiscountCalculationPrice(10.0);
    }

    $collector = new class ([$parent, ...$children]) extends Mage_SalesRule_Model_Quote_Discount {
        public function __construct(private readonly array $items)
        {
            parent::__construct();
        }

        #[\Override]
        protected function _getAddressItems(Mage_Sales_Model_Quote_Address $address): array
        {
            return $this->items;
        }
    };
    $collect = static fn() => validatorDiscountObserve(
        $eventName,
        static function (): void {},
        static fn() => $collector->collect($address),
    );
    $collect();
    expect($children[0]->getBaseDiscountAmount())->toBe(2.0)
        ->and($children[1]->getBaseDiscountAmount())->toBe(2.0)
        ->and($children[2]->getBaseDiscountAmount())->toBe(2.01);

    $children[0]->setNoDiscount(true);
    $collect();
    expect($children[0]->getBaseDiscountAmount())->toBe(0.0)
        ->and($children[1]->getBaseDiscountAmount())->toBe(3.01)
        ->and($children[2]->getBaseDiscountAmount())->toBe(3.0)
        ->and($address->getBaseTotalAmount('discount'))->toBe(-6.01);

    $parent->setNoDiscount(true);
    $collect();
    expect($children[0]->getBaseDiscountAmount())->toBe(0.0)
        ->and($children[1]->getBaseDiscountAmount())->toBe(0.0)
        ->and($children[2]->getBaseDiscountAmount())->toBe(0.0)
        ->and($address->getBaseTotalAmount('discount'))->toEqual(0.0);
})->with(['unrelated_discount_event', 'sales_quote_address_discount_item']);

it('calls an overridden item processor for a fixed cart discount', function (): void {
    $groupId = (int) $this->group->getId();
    validatorDiscountRule($groupId, Mage_SalesRule_Model_Rule::CART_FIXED_ACTION, 3.0);
    $store = Mage::app()->getStore(1);
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setCustomerGroupId($groupId);
    $quote->addProduct(validatorDiscountProduct(10.0, $store), 1);
    $items = $quote->getAllVisibleItems();
    $address = $quote->getBillingAddress();
    $calculator = new class extends Mage_SalesRule_Model_Validator {
        public array $processed = [];

        #[\Override]
        public function process(Mage_Sales_Model_Quote_Item_Abstract $item): static
        {
            $this->processed[] = $item;
            return $this;
        }
    };
    $calculator->reset($address)->init((int) $store->getWebsiteId(), $groupId, '')->initTotals($items, $address);

    expect(iterator_to_array($calculator->processItems($items), false))->toBe($items)
        ->and($calculator->processed)->toBe($items);
});
