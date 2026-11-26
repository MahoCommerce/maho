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
): Mage_SalesRule_Model_Rule {
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
    return $rule->save();
}

function addCartFeeEligibilityProduct(
    Mage_Sales_Model_Quote $quote,
    string $feeAttribute,
    float $qty,
    float $price = 100.0,
): Mage_Sales_Model_Quote_Item {
    $store = $quote->getStore();
    $fixture = WeeeDiscountFixture::createProduct(
        'eligible-fee-' . uniqid(),
        $feeAttribute,
        [(int) $store->getWebsiteId()],
        price: $price,
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

it('applies stacked percentages to the remaining price of the eligible units', function (
    float $qty,
    array $rules,
    float $feeDiscount,
    float $total,
): void {
    $item = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, $qty);
    foreach ($rules as $priority => [$rate, $maximumQty, $step]) {
        cartFeeEligibilityRule($this->websiteId, $this->groupId, $rate, $priority, maximumQty: $maximumQty, step: $step);
    }

    $this->cart->collectAndSave($this->quote);

    expect($item->getDiscountAmount())->toEqualWithDelta($feeDiscount * 10.0, 0.0001)
        ->and($item->getBaseDiscountAmount())->toEqualWithDelta($feeDiscount * 10.0, 0.0001)
        ->and($item->getWeeeDiscount())->toEqualWithDelta($feeDiscount, 0.0001)
        ->and($item->getBaseWeeeDiscount())->toEqualWithDelta($feeDiscount, 0.0001)
        ->and($this->quote->getGrandTotal())->toEqualWithDelta($total, 0.0001);
})->with([
    'limited discount first' => [5.0, [[50.0, 3.0, 2], [20.0, null, null]], 18.0, 352.0],
    'full-line discount first' => [5.0, [[20.0, null, null], [50.0, 3.0, 2]], 18.0, 352.0],
    'previous discount exceeds the eligible units gross price' => [5.0, [[60.0, null, null], [50.0, 3.0, 2]], 34.0, 176.0],
    'later discount covers a wider subset' => [5.0, [[20.0, null, null], [50.0, 2.0, null], [25.0, 3.0, null]], 22.0, 308.0],
    'no complete step for the later rule' => [1.0, [[20.0, null, null], [50.0, null, 2]], 2.0, 88.0],
]);

it('uses the discount rate of a registered to-percent rule for both merchandise and fixed product taxes', function (): void {
    Mage::getConfig()->setNode(Mage_SalesRule_Model_Rule::XML_PATH_SIMPLE_ACTIONS . '/to_percent', '1');
    $item = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, 5.0);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 20.0, 0);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 75.0, 1, Mage_SalesRule_Model_Rule::TO_PERCENT_ACTION, maximumQty: 2.0);

    $this->cart->collectAndSave($this->quote);

    expect($item->getDiscountAmount())->toEqualWithDelta(140.0, 0.0001)
        ->and($item->getWeeeDiscount())->toEqualWithDelta(14.0, 0.0001)
        ->and($this->quote->getGrandTotal())->toEqualWithDelta(396.0, 0.0001);
});

it('prorates a prior fixed item discount over the units eligible for a capped percentage', function (): void {
    $item = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, 5.0);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 10.0, 0, Mage_SalesRule_Model_Rule::BY_FIXED_ACTION);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 20.0, 1, maximumQty: 2.0);

    $this->cart->collectAndSave($this->quote);

    expect($item->getDiscountAmount())->toEqualWithDelta(86.0, 0.0001)
        ->and($item->getWeeeDiscount())->toEqualWithDelta(4.0, 0.0001)
        ->and($this->quote->getGrandTotal())->toEqualWithDelta(460.0, 0.0001);
});

it('keeps the capped percentage discount in whole cents when the eligible capacity includes a fraction of a cent', function (): void {
    $item = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, 3.0, price: 1.0);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 0.01, 0, Mage_SalesRule_Model_Rule::CART_FIXED_ACTION);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 100.0, 1, maximumQty: 1.0);

    $this->cart->collectAndSave($this->quote);
    $reloaded = $this->cart->getById((int) $this->quote->getId());
    $savedItem = $reloaded->getAllVisibleItems()[0];

    expect($item->getDiscountAmount())->toBe(1.0)
        ->and($item->getBaseDiscountAmount())->toBe(1.0)
        ->and($savedItem->getDiscountAmount())->toBe(1.0)
        ->and($savedItem->getBaseDiscountAmount())->toBe(1.0)
        ->and($item->getWeeeDiscount())->toBe(10.0)
        ->and($reloaded->getGrandTotal())->toBe(22.0);
});

it('keeps fixed product tax on the units of a buy X get Y item rule', function (float $freeQty, ?int $step, float $discount): void {
    $item = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, 5.0);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, $freeQty, 0, Mage_SalesRule_Model_Rule::BUY_X_GET_Y_ACTION, step: $step, stop: true);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 50.0, 1);

    $this->cart->collectAndSave($this->quote);

    expect($item->getDiscountAmount())->toEqualWithDelta($discount, 0.0001)
        ->and($item->getWeeeDiscount())->toEqualWithDelta(0.0, 0.0001)
        ->and($this->quote->getGrandTotal())->toEqualWithDelta(550.0 - $discount, 0.0001);
})->with([
    'one free unit' => [1.0, 2, 100.0],
    'no buy step' => [1.0, null, 0.0],
    'free quantity exceeds the buy step' => [3.0, 2, 0.0],
]);

it('retains the earlier discount when its coverage on the eligible units cannot be proven', function (
    string $action,
    float $qty,
    float $amount,
    ?float $maximumQty,
    ?int $step,
    float $expected,
): void {
    $item = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, $qty, price: 10.0);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, $amount, 0, $action, maximumQty: $maximumQty, step: $step);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 100.0, 1, maximumQty: 1.0);

    $this->cart->collectAndSave($this->quote);

    expect($item->getDiscountAmount())->toBe($expected)
        ->and($item->getBaseDiscountAmount())->toBe($expected)
        ->and($item->getWeeeDiscount())->toBe(10.0)
        ->and($this->quote->getGrandTotal())->toBe($qty * 20.0 - $expected - 10.0);
})->with([
    'one unit already receives a fixed discount' => [Mage_SalesRule_Model_Rule::BY_FIXED_ACTION, 2.0, 10.0, 1.0, null, 10.0],
    'the prior fixed discount exceeds the eligible price' => [Mage_SalesRule_Model_Rule::BY_FIXED_ACTION, 4.0, 6.0, 2.0, null, 12.0],
    'one unit is free under buy X get Y' => [Mage_SalesRule_Model_Rule::BUY_X_GET_Y_ACTION, 3.0, 1.0, null, 2, 10.0],
]);

it('uses the existing amount when a discount observer replaces a rule result', function (): void {
    $item = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, 2.0, price: 10.0);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 0.0, 0);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 100.0, 1, maximumQty: 1.0);

    $observer = new class {
        public function adjust(Maho\Event\Observer $observer): void
        {
            if ($observer->getRule()->getDiscountAmount() === 0.0) {
                $observer->getResult()->setDiscountAmount(10.0)->setBaseDiscountAmount(10.0);
            }
        }
    };
    $alias = 'cart_fee_eligibility_test/observer';
    $events = new ReflectionProperty(Mage::app(), '_events');
    $originalEvents = $events->getValue(Mage::app());
    $config = $originalEvents;
    $config['global']['salesrule_validator_process'] = ['observers' => [
        'cart_fee_eligibility_test' => ['type' => 'singleton', 'model' => $alias, 'method' => 'adjust'],
    ]];
    Mage::register('_singleton/' . $alias, $observer);
    $events->setValue(Mage::app(), $config);
    try {
        $this->cart->collectAndSave($this->quote);
    } finally {
        $events->setValue(Mage::app(), $originalEvents);
        Mage::unregister('_singleton/' . $alias);
    }

    expect($item->getDiscountAmount())->toBe(10.0)
        ->and($item->getBaseDiscountAmount())->toBe(10.0)
        ->and($this->quote->getGrandTotal())->toBe(20.0);
});

it('discounts fixed product taxes for only the current rule ids recorded by a rewritten process', function (bool $callParent, bool $rewriteRules = false): void {
    $rule = cartFeeEligibilityRule(
        $this->websiteId,
        $rewriteRules ? Mage_Customer_Model_Group::NOT_LOGGED_IN_ID : $this->groupId,
        50.0,
        0,
        sku: 'rewrite-only-item',
        maximumQty: 3.0,
        step: 2,
    );
    $foreignRule = cartFeeEligibilityRule($this->websiteId, Mage_Customer_Model_Group::NOT_LOGGED_IN_ID, 50.0, 1);
    $validator = new class ((string) $rule->getId(), (string) $foreignRule->getId(), $callParent, $rewriteRules) extends Mage_SalesRule_Model_Validator {
        public ?string $sku = null;
        public bool $recordRules = true;

        public function __construct(
            private readonly string $ruleId,
            private readonly string $foreignRuleId,
            private readonly bool $callParent,
            private readonly bool $rewriteRules,
        ) {}

        #[\Override]
        public function process(Mage_Sales_Model_Quote_Item_Abstract $item): static
        {
            if ($this->callParent) {
                parent::process($item);
            }
            $applied = $this->recordRules && $item->getSku() === $this->sku;
            $discount = $applied && !$this->callParent ? 100.0 : 0.0;
            $item->setDiscountAmount($discount)->setBaseDiscountAmount($discount);
            if ($applied) {
                $item->setAppliedRuleIds($this->ruleId . ',' . $this->foreignRuleId);
            }
            return $this;
        }

        #[\Override]
        protected function _getRules()
        {
            return $this->rewriteRules
                ? Mage::getResourceModel('salesrule/rule_collection')->addFieldToFilter('rule_id', $this->ruleId)
                : parent::_getRules();
        }

        #[\Override]
        protected function _getItemQty($item, $rule)
        {
            if (!$this->callParent) {
                throw new RuntimeException('The fixed tax calculation must not call the rewrite again.');
            }
            return parent::_getItemQty($item, $rule);
        }
    };
    $key = '_singleton/salesrule/validator';
    $originalValidator = Mage::registry($key);
    Mage::unregister($key);
    Mage::register($key, $validator);
    try {
        $eligible = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, 5.0);
        $other = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, 2.0);
        $validator->sku = $eligible->getSku();
        $other->setAppliedRuleIds((string) $rule->getId());

        $this->cart->collectAndSave($this->quote);

        expect($eligible->getDiscountAmount())->toBe($callParent ? 0.0 : 100.0)
            ->and($eligible->getWeeeDiscount())->toBe($callParent ? 0.0 : 10.0)
            ->and($eligible->getBaseWeeeDiscount())->toBe($callParent ? 0.0 : 10.0)
            ->and($other->getWeeeDiscount())->toBe(0.0)
            ->and($this->quote->getGrandTotal())->toBe($callParent ? 770.0 : 660.0);

        $validator->recordRules = false;
        $this->cart->collectAndSave($this->quote);

        expect($eligible->getWeeeDiscount())->toBe(0.0)
            ->and($other->getWeeeDiscount())->toBe(0.0)
            ->and($this->quote->getGrandTotal())->toBe(770.0);
    } finally {
        Mage::unregister($key);
        if ($originalValidator !== null) {
            Mage::register($key, $originalValidator);
        }
    }
})->with([
    'rewrite declares currently applied rules without invoking the parent' => [false],
    'rewrite calls the parent, which checks and rejects the percentage rule' => [true],
    'rewrite supplies the active rule collection' => [false, true],
]);

it('consults the fixed tax helper rewrite when applying a cart rule', function (): void {
    $item = addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, 1.0);
    cartFeeEligibilityRule($this->websiteId, $this->groupId, 20.0, 0);
    Mage::unregister('_helper/weee');
    Mage::register('_helper/weee', new class extends Mage_Weee_Helper_Data {
        #[\Override]
        public function getApplied($item): array
        {
            return $item instanceof Mage_Sales_Model_Quote_Item_Abstract ? [] : parent::getApplied($item);
        }
    });

    $this->cart->collectAndSave($this->quote);

    expect($item->getDiscountAmount())->toBe(20.0)
        ->and($item->getWeeeTaxAppliedRowAmount())->toEqualWithDelta(10.0, 0.00001)
        ->and($item->getWeeeDiscount())->toBe(0.0);
});

it('calls a rewritten fixed tax processor only when tax discounts are enabled', function (bool $enabled, bool $discounted): void {
    $store = $this->quote->getStore();
    $store->setConfig(Mage_Weee_Helper_Data::XML_PATH_FPT_ENABLED, $enabled ? '1' : '0');
    $store->setConfig('tax/weee/discount', $discounted ? '1' : '0');
    $validator = new class extends Mage_SalesRule_Model_Validator {
        public int $calls = 0;

        #[\Override]
        public function processWeeeAmount(Mage_Sales_Model_Quote_Address $address, $items): static
        {
            $this->calls++;
            return $this;
        }
    };
    Mage::unregister('_singleton/salesrule/validator');
    Mage::register('_singleton/salesrule/validator', $validator);
    addCartFeeEligibilityProduct($this->quote, $this->feeAttribute, 1.0);

    $this->quote->collectTotals();

    expect($validator->calls)->toBe($enabled && $discounted ? 1 : 0);
})->with([
    'fixed product taxes disabled' => [false, true],
    'fixed product tax discounts disabled' => [true, false],
    'fixed product taxes and discounts enabled' => [true, true],
]);
