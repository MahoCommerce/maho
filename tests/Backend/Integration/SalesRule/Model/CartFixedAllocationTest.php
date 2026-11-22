<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

use Mage_SalesRule_Model_Rule as Rule;

uses(Tests\MahoBackendTestCase::class);

it('uses the fixed cart budget within the remaining capacity of every eligible line', function (
    array $lines,
    array $rules,
    float $expectedBaseTotal,
    float $expectedTotal,
    string $currency = 'USD',
    ?float $shippingPrice = null,
    ?array $expectedLineDiscounts = null,
    ?array $expectedPersisted = null,
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    $previousShippingConfig = [];
    $eventProperty = null;
    $originalEvents = null;
    $observerAlias = 'cart_fixed_allocation_test/observer';
    try {
        Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => $expectedPersisted['rate'] ?? 2.0]]);
        $store = setStoreDisplayCurrency($currency, 'USD,EUR');
        $storeId = (int) $store->getId();
        $websiteId = (int) $store->getWebsiteId();
        $group = Mage::getModel('customer/group')
            ->setCode('Fixed allocation ' . uniqid())
            ->setTaxClassId((int) Mage::getModel('customer/group')->getTaxClassId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID))
            ->save();
        foreach ($rules as $priority => [$action, $discount, $maxQty]) {
            Mage::getModel('salesrule/rule')
                ->setName('Fixed allocation ' . uniqid())
                ->setIsActive(true)
                ->setWebsiteIds([$websiteId])
                ->setCustomerGroupIds([(int) $group->getId()])
                ->setCouponType(Rule::COUPON_TYPE_NO_COUPON)
                ->setSimpleAction($action)
                ->setDiscountAmount($discount)
                ->setDiscountQty($maxQty)
                ->setSortOrder($priority)
                ->setApplyToShipping($shippingPrice !== null && $action === Rule::CART_FIXED_ACTION)
                ->setStopRulesProcessing(false)
                ->save();
        }

        $cart = Mage::getService('checkout/cart');
        $quote = $cart->create(null, $storeId)->setCustomerGroupId((int) $group->getId());
        if ($shippingPrice !== null) {
            foreach (['active' => '1', 'price' => (string) $shippingPrice, 'type' => 'O'] as $field => $value) {
                $path = 'carriers/flatrate/' . $field;
                $previousShippingConfig[$path] = $store->getConfig($path);
                $store->setConfig($path, $value);
            }
            $quote->getShippingAddress()->setCountryId('US')->setPostcode('10001')->setShippingMethod('flatrate_flatrate');
        }
        foreach ($lines as [$price, $qty]) {
            $product = Mage::getModel('catalog/product')->setStoreId(0);
            $product->setAttributeSetId($product->getDefaultAttributeSetId())
                ->setTypeId($shippingPrice === null ? Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL : Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
                ->setSku('fixed-allocation-' . uniqid())
                ->setName('Fixed allocation item')
                ->setPrice($price)
                ->setWeight(1.0)
                ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
                ->setTaxClassId(0)
                ->setWebsiteIds([$websiteId])
                ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100])
                ->save();
            $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($product->getId());
            $cart->addProduct($quote, $product, new Maho\DataObject(['qty' => $qty]), save: false);
        }

        if (isset($expectedPersisted['callback'])) {
            $observer = new readonly class ($expectedPersisted['callback']) {
                public function __construct(private array $amounts) {}

                public function record(Maho\Event\Observer $observer): void
                {
                    if ($observer->getRule()->getSimpleAction() === Rule::CART_FIXED_ACTION) {
                        $observer->getResult()->setBaseDiscountAmount($this->amounts[0])->setDiscountAmount($this->amounts[1]);
                    }
                }
            };
            $app = Mage::app();
            $eventProperty = new ReflectionProperty($app, '_events');
            $originalEvents = $eventProperty->getValue($app);
            $events = $originalEvents;
            $events['global']['salesrule_validator_process'] = ['observers' => [
                'fixed_test' => ['type' => 'singleton', 'model' => $observerAlias, 'method' => 'record'],
            ]];
            Mage::register('_singleton/' . $observerAlias, $observer);
            $eventProperty->setValue($app, $events);
        }

        for ($pass = 0; $pass < 2; $pass++) {
            if ($shippingPrice !== null) {
                $quote->getShippingAddress()->setCollectShippingRates(true);
            }
            $cart->collectAndSave($quote);
            if ($shippingPrice !== null) {
                expect($quote->getShippingAddress()->getBaseShippingAmount())->toEqualWithDelta($shippingPrice, 0.0001);
            }
            expect($quote->getBaseGrandTotal())->toEqualWithDelta($expectedBaseTotal, 0.0001)
                ->and($quote->getGrandTotal())->toEqualWithDelta($expectedTotal, 0.0001);
            if ($expectedLineDiscounts !== null && $expectedPersisted === null) {
                $baseDiscounts = array_map(
                    static fn(Mage_Sales_Model_Quote_Item $item): float => (float) $item->getBaseDiscountAmount(),
                    $quote->getAllVisibleItems(),
                );
                expect($baseDiscounts)->toBe($expectedLineDiscounts);
            }
            if ($expectedPersisted !== null) {
                $address = $quote->getBillingAddress();
                expect(array_sum($address->getCartFixedRules()))->toEqualWithDelta($expectedPersisted['remaining'][0], 0.000000001)
                    ->and(array_sum($address->getData('cart_fixed_display_rules')))->toEqualWithDelta($expectedPersisted['remaining'][1], 0.000000001);

                $reloaded = $cart->getById((int) $quote->getId());
                foreach ([$quote, $reloaded] as $currentQuote) {
                    foreach ($currentQuote->getAllVisibleItems() as $index => $item) {
                        expect($item->getBaseDiscountAmount())->toEqualWithDelta($expectedLineDiscounts[$index], 0.000000001)
                            ->and($item->getDiscountAmount())->toEqualWithDelta($expectedPersisted['lines'][$index], 0.000000001);
                    }
                    $currentAddress = $currentQuote->getBillingAddress();
                    expect($currentAddress->getBaseDiscountAmount())->toEqualWithDelta(-array_sum($expectedLineDiscounts), 0.000000001)
                        ->and($currentAddress->getDiscountAmount())->toEqualWithDelta(-array_sum($expectedPersisted['lines']), 0.000000001)
                        ->and($currentAddress->getBaseGrandTotal())->toEqualWithDelta($expectedBaseTotal, 0.000000001)
                        ->and($currentAddress->getGrandTotal())->toEqualWithDelta($expectedTotal, 0.000000001);
                }
                $quote = $reloaded;
            }
        }
    } finally {
        if ($eventProperty !== null) {
            $eventProperty->setValue(Mage::app(), $originalEvents);
            Mage::unregister('_singleton/' . $observerAlias);
        }
        $connection->rollBack();
        foreach ($previousShippingConfig as $path => $value) {
            $store->setConfig($path, $value);
        }
        resetCurrencyState();
    }
})->with([
    'fully discounted first line' => [
        [[0.01, 1], [0.03, 1]], [[Rule::BY_PERCENT_ACTION, 50.0, null], [Rule::CART_FIXED_ACTION, 0.02, null]], 0.0, 0.0,
    ],
    'fully discounted second line' => [
        [[0.03, 1], [0.01, 1]], [[Rule::BY_PERCENT_ACTION, 50.0, null], [Rule::CART_FIXED_ACTION, 0.02, null]], 0.0, 0.0,
    ],
    'different available capacities in the base and display currencies' => [
        [[0.01, 1], [0.03, 1]], [[Rule::BY_PERCENT_ACTION, 50.0, null], [Rule::CART_FIXED_ACTION, 0.02, null]], 0.0, 0.0, 'EUR',
    ],
    'later line has no capacity after the limited percentage promotion' => [
        [[0.10, 2], [0.10, 1]], [[Rule::BY_PERCENT_ACTION, 100.0, 1.0], [Rule::CART_FIXED_ACTION, 0.10, null]], 0.0, 0.0,
    ],
    'earlier line has no capacity after the limited percentage promotion' => [
        [[0.10, 1], [0.10, 2]], [[Rule::BY_PERCENT_ACTION, 100.0, 1.0], [Rule::CART_FIXED_ACTION, 0.10, null]], 0.0, 0.0,
    ],
    'fixed rule uses one unit and preserves the preceding discount on both' => [
        [[0.20, 2]], [[Rule::BY_PERCENT_ACTION, 50.0, null], [Rule::CART_FIXED_ACTION, 0.10, 1.0]], 0.10, 0.10,
    ],
    'two fixed credits each use the remaining capacity after the previous rule' => [
        [[0.01, 1], [0.03, 1]], [
            [Rule::BY_PERCENT_ACTION, 50.0, null],
            [Rule::CART_FIXED_ACTION, 0.01, null],
            [Rule::CART_FIXED_ACTION, 0.01, null],
        ], 0.0, 0.0,
    ],
    'following percentage uses the final allocation of the fixed credit' => [
        [[0.10, 2], [0.10, 1]], [
            [Rule::BY_PERCENT_ACTION, 100.0, 1.0],
            [Rule::CART_FIXED_ACTION, 0.06, null],
            [Rule::BY_PERCENT_ACTION, 50.0, null],
        ], 0.02, 0.02,
    ],
    'no fixed discount is allocated on entirely free lines' => [
        [[0.0, 1], [0.0, 1]], [[Rule::CART_FIXED_ACTION, 0.10, null]], 0.0, 0.0,
    ],
    'shipping uses the remaining credit in each currency' => [
        [[0.01, 1]], [[Rule::BY_PERCENT_ACTION, 50.0, null], [Rule::CART_FIXED_ACTION, 0.02, null]], 0.01, 0.03, 'EUR', 0.03,
    ],
    'last eligible line receives the rounding remainder' => [
        [[1.0, 1], [1.0, 1], [1.0, 1]], [[Rule::CART_FIXED_ACTION, 0.01, null]], 2.99, 2.99, 'USD', null, [0.0, 0.0, 0.01],
    ],
    'remaining lines share the unused credit proportionally' => [
        [[10.0, 1], [5.0, 2], [5.0, 2]], [[Rule::BY_PERCENT_ACTION, 100.0, 1.0], [Rule::CART_FIXED_ACTION, 6.0, null]], 4.0, 4.0, 'USD', null, [10.0, 8.0, 8.0],
    ],
    'a percentage on one unit preserves the fixed discount shared by both units' => [
        [[10.0, 2]], [[Rule::CART_FIXED_ACTION, 15.0, null], [Rule::BY_PERCENT_ACTION, 50.0, 1.0]], 3.75, 7.5, 'EUR', null, [16.25],
    ],
    'fractional capacity is floored independently in each currency' => [
        [[10.0, 3]], [[Rule::BY_FIXED_ACTION, 1.0, 1.0], [Rule::CART_FIXED_ACTION, 10.0, 1.0]],
        19.34, 14.12, 'EUR', null, [10.66],
        ['rate' => 0.73, 'remaining' => [0.34, 0.25], 'lines' => [7.78]],
    ],
    'another line absorbs the credit left by a fractional cap' => [
        [[10.0, 3], [40.0, 1]], [[Rule::BY_FIXED_ACTION, 1.0, 1.0], [Rule::CART_FIXED_ACTION, 48.57, 1.0]],
        19.43, 14.18, 'EUR', null, [10.66, 39.91],
        ['rate' => 0.73, 'remaining' => [0.0, 0.0], 'lines' => [7.78, 29.14]],
    ],
    'the credit stays available when each line has less than one cent of capacity' => [
        [[0.03, 3], [0.03, 3]], [[Rule::BY_PERCENT_ACTION, 80.0, null], [Rule::CART_FIXED_ACTION, 0.01, 1.0]],
        0.04, 0.04, 'USD', null, [0.07, 0.07],
        ['rate' => 0.73, 'remaining' => [0.01, 0.01], 'lines' => [0.07, 0.07]],
    ],
    'fractional amounts returned by a fixed discount observer are rounded once' => [
        [[10.0, 1]], [[Rule::CART_FIXED_ACTION, 2.0, null]],
        8.77, 6.40, 'EUR', null, [1.23],
        ['rate' => 0.73, 'remaining' => [0.77, 0.56], 'lines' => [0.90], 'callback' => [1.234, 0.904]],
    ],
]);
