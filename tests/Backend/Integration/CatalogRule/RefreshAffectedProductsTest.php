<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

use Tests\Helpers\WeeeDiscountFixture;

uses(Tests\MahoBackendTestCase::class);

function wapCreateFeeAttribute(string $code): void
{
    WeeeDiscountFixture::createFeeAttribute($code);
}

function wapCreateProduct(string $sku, string $attributeCode, array $websiteIds = [1]): array
{
    return WeeeDiscountFixture::createProduct($sku, $attributeCode, $websiteIds);
}

function wapCreateRule(
    Mage_Catalog_Model_Product $product,
    float $amount = 10.0,
    int $sortOrder = 0,
    bool $stop = false,
    array $websiteIds = [1],
    array $customerGroupIds = [0],
    ?string $matchedSku = null,
    string $action = 'by_percent',
): Mage_CatalogRule_Model_Rule {
    return WeeeDiscountFixture::createRule($product, 'WAP 10 ' . uniqid(), $action, $amount, $sortOrder, $stop, $websiteIds, $customerGroupIds, $matchedSku);
}

function wapApplyAllRules(): void
{
    Mage::getModel('catalogrule/rule')->applyAll();
}

/**
 * @return list<array<string, mixed>>
 */
function wapDiscountRows(int $productId): array
{
    $read = Mage::getSingleton('core/resource')->getConnection('core_read');
    $table = Mage::getSingleton('core/resource')->getTableName('weee/discount');
    return $read->fetchAll(
        "SELECT entity_id, website_id, customer_group_id, value FROM {$table} WHERE entity_id = ? ORDER BY website_id, customer_group_id",
        [$productId],
    );
}

function wapRuntimeFpt(int $productId, int $websiteId = 1, int $storeId = 1, int $groupId = 0): float
{
    $loaded = Mage::getModel('catalog/product')->setStoreId($storeId)->load($productId)->setCustomerGroupId($groupId);
    return (float) Mage::getModel('weee/tax')->getWeeeAmount($loaded, null, null, $websiteId, false, false);
}

/**
 * @return list<array<string, mixed>>
 */
function wapRulePriceRows(int $productId): array
{
    $read = Mage::getSingleton('core/resource')->getConnection('core_read');
    $table = Mage::getSingleton('core/resource')->getTableName('catalogrule/rule_product_price');
    return $read->fetchAll(
        "SELECT rule_date, website_id, customer_group_id, rule_price FROM {$table} WHERE product_id = ? ORDER BY rule_date, website_id, customer_group_id",
        [$productId],
    );
}

function wapSetRuleSku(Mage_CatalogRule_Model_Rule $rule, string $sku): void
{
    $rule->loadPost(['conditions' => [
        '1' => ['type' => 'catalogrule/rule_condition_combine', 'aggregator' => 'all', 'value' => '1', 'new_child' => ''],
        '1--1' => ['type' => 'catalogrule/rule_condition_product', 'attribute' => 'sku', 'operator' => '==', 'value' => $sku],
    ]]);
}

beforeEach(function (): void {
    $GLOBALS['wap_cleanup'] = ['products' => [], 'categories' => [], 'rules' => [], 'attribute' => null];
    $this->wapPreviousStore = Mage::app()->getStore()->getId();
    Mage::app()->setCurrentStore(1);
    Mage::app()->getArea('global')->load(Mage_Core_Model_App_Area::PART_EVENTS);

    $token = substr(hash('sha256', uniqid('', true)), 0, 8);
    $this->wapAttributeCode = 'wap_fee_' . $token;
    $GLOBALS['wap_cleanup']['attribute'] = $this->wapAttributeCode;
    wapCreateFeeAttribute($this->wapAttributeCode);

    $this->wapStore = Mage::app()->getStore(1);
    $this->wapPreviousConfig = [];
    foreach ([1, 2] as $websiteId) {
        $store = Mage::app()->getWebsite($websiteId)->getDefaultGroup()->getDefaultStore();
        foreach (['tax/weee/enable', 'tax/weee/discount', 'tax/weee/display_list'] as $path) {
            $this->wapPreviousConfig[$store->getId()][$path] = $store->getConfig($path);
        }
        $store->setConfig('tax/weee/enable', '1');
        $store->setConfig('tax/weee/discount', '1');
        $store->setConfig('tax/weee/display_list', Mage_Weee_Model_Tax::DISPLAY_INCL);
    }
});

afterEach(function (): void {
    WeeeDiscountFixture::cleanup($GLOBALS['wap_cleanup']);
    foreach ($this->wapPreviousConfig ?? [] as $storeId => $values) {
        foreach ($values as $path => $value) {
            Mage::app()->getStore((int) $storeId)->setConfig($path, $value);
        }
    }
    Mage::app()->setCurrentStore($this->wapPreviousStore ?? Mage_Core_Model_App::ADMIN_STORE_ID);
});

it('refreshes native FPT discounts after a catalog rule refresh', function (): void {
    $fixture = wapCreateProduct('WAP-' . uniqid(), $this->wapAttributeCode);
    $product = $fixture['product'];
    $productId = (int) $product->getId();
    $GLOBALS['wap_cleanup']['categories'][] = (int) $fixture['category']->getId();
    $GLOBALS['wap_cleanup']['products'][] = $productId;
    $rule = wapCreateRule($product);
    $GLOBALS['wap_cleanup']['rules'][] = (int) $rule->getId();
    wapApplyAllRules();

    $read = Mage::getSingleton('core/resource')->getConnection('core_read');
    $resources = Mage::getSingleton('core/resource');
    $rulePriceTable = $resources->getTableName('catalogrule/rule_product_price');
    $priceIndexTable = $resources->getTableName('catalog/product_index_price');
    $rulePrice = $read->fetchRow(
        "SELECT rule_price FROM {$rulePriceTable} WHERE product_id = ? AND website_id = ? AND customer_group_id = ? AND rule_date = ?",
        [$productId, 1, 0, Mage::app()->getLocale()->formatDateForDb(time(), withTime: false)],
    );
    $priceIndex = $read->fetchRow(
        "SELECT final_price, min_price FROM {$priceIndexTable} WHERE entity_id = ? AND website_id = ? AND customer_group_id = ?",
        [$productId, 1, 0],
    );
    $runtimeFpt = wapRuntimeFpt($productId);
    $discountRows = wapDiscountRows($productId);
    expect($runtimeFpt)->toBe(9.0);
    expect((float) ($rulePrice['rule_price'] ?? 0))->toBe(72.0);
    expect((float) ($priceIndex['min_price'] ?? 0))->toBe(72.0);
    expect($discountRows)->toHaveCount(1);
    expect((float) $discountRows[0]['value'])->toBe(90.0);
});

it('rebuilds the FPT remainder when a matched percentage rule changes', function (): void {
    $fixture = wapCreateProduct('WAP-CHANGE-' . uniqid(), $this->wapAttributeCode);
    $product = $fixture['product'];
    $productId = (int) $product->getId();
    $GLOBALS['wap_cleanup']['categories'][] = (int) $fixture['category']->getId();
    $GLOBALS['wap_cleanup']['products'][] = $productId;
    $rule = wapCreateRule($product);
    $GLOBALS['wap_cleanup']['rules'][] = (int) $rule->getId();

    wapApplyAllRules();
    expect((float) wapDiscountRows($productId)[0]['value'])->toBe(90.0);
    expect(wapRuntimeFpt($productId))->toBe(9.0);

    $rule->setDiscountAmount(20)->save();
    wapApplyAllRules();

    expect((float) wapDiscountRows($productId)[0]['value'])->toBe(80.0);
    expect(wapRuntimeFpt($productId))->toBe(8.0);
});

it('preserves stacked, stop, fixed amount, and unrelated product controls', function (): void {
    $stacked = wapCreateProduct('WAP-STACK-' . uniqid(), $this->wapAttributeCode);
    $stopped = wapCreateProduct('WAP-STOP-' . uniqid(), $this->wapAttributeCode);
    $fixed = wapCreateProduct('WAP-FIXED-' . uniqid(), $this->wapAttributeCode);
    $unrelated = wapCreateProduct('WAP-UNRELATED-' . uniqid(), $this->wapAttributeCode);
    $products = [$stacked['product'], $stopped['product'], $fixed['product'], $unrelated['product']];
    foreach ([$stacked, $stopped, $fixed, $unrelated] as $fixture) {
        $GLOBALS['wap_cleanup']['categories'][] = (int) $fixture['category']->getId();
    }
    foreach ($products as $product) {
        $GLOBALS['wap_cleanup']['products'][] = (int) $product->getId();
    }

    $rules = [
        wapCreateRule($stacked['product'], 33, 0),
        wapCreateRule($stacked['product'], 10, 1),
        wapCreateRule($stopped['product'], 10, 0, true),
        wapCreateRule($stopped['product'], 20, 1),
        wapCreateRule($fixed['product'], 5, 0, false, [1], [0], null, 'by_fixed'),
    ];
    foreach ($rules as $rule) {
        $GLOBALS['wap_cleanup']['rules'][] = (int) $rule->getId();
    }

    wapApplyAllRules();

    $stackedId = (int) $stacked['product']->getId();
    $stoppedId = (int) $stopped['product']->getId();
    $fixedId = (int) $fixed['product']->getId();
    $unrelatedId = (int) $unrelated['product']->getId();
    expect((float) wapDiscountRows($stackedId)[0]['value'])->toBe(60.3);
    expect(wapRuntimeFpt($stackedId))->toBe(6.0);
    expect((float) wapDiscountRows($stoppedId)[0]['value'])->toBe(90.0);
    expect(wapRuntimeFpt($stoppedId))->toBe(9.0);
    expect(wapDiscountRows($fixedId))->toBeEmpty();
    expect(wapRuntimeFpt($fixedId))->toBe(10.0);
    expect(wapDiscountRows($unrelatedId))->toBeEmpty();
    expect(wapRuntimeFpt($unrelatedId))->toBe(10.0);
});

it('keeps the existing one hundred percent zero sentinel behavior', function (): void {
    $fixture = wapCreateProduct('WAP-100-' . uniqid(), $this->wapAttributeCode);
    $productId = (int) $fixture['product']->getId();
    $GLOBALS['wap_cleanup']['categories'][] = (int) $fixture['category']->getId();
    $GLOBALS['wap_cleanup']['products'][] = $productId;
    $rule = wapCreateRule($fixture['product'], 100);
    $GLOBALS['wap_cleanup']['rules'][] = (int) $rule->getId();

    wapApplyAllRules();

    expect(wapDiscountRows($productId))->toHaveCount(1);
    expect((float) wapDiscountRows($productId)[0]['value'])->toBe(0.0);
    expect(wapRuntimeFpt($productId))->toBe(10.0);
});

it('removes stale FPT discounts when rules are disabled, deleted, or unmatched', function (): void {
    $fixtures = [
        'disabled' => wapCreateProduct('WAP-DISABLE-' . uniqid(), $this->wapAttributeCode),
        'deleted' => wapCreateProduct('WAP-DELETE-' . uniqid(), $this->wapAttributeCode),
        'unmatched' => wapCreateProduct('WAP-UNMATCH-' . uniqid(), $this->wapAttributeCode),
    ];
    $rules = [];
    foreach ($fixtures as $name => $fixture) {
        $product = $fixture['product'];
        $GLOBALS['wap_cleanup']['categories'][] = (int) $fixture['category']->getId();
        $GLOBALS['wap_cleanup']['products'][] = (int) $product->getId();
        $rules[$name] = wapCreateRule($product);
        $GLOBALS['wap_cleanup']['rules'][] = (int) $rules[$name]->getId();
    }
    wapApplyAllRules();

    $productIds = array_map(static fn(array $fixture): int => (int) $fixture['product']->getId(), $fixtures);
    foreach ($productIds as $productId) {
        expect(wapDiscountRows($productId))->toHaveCount(1);
    }

    $rules['disabled']->setIsActive(false)->save();
    $rules['deleted']->delete();
    wapSetRuleSku($rules['unmatched'], 'WAP-NO-MATCH-' . uniqid());
    $rules['unmatched']->save();
    wapApplyAllRules();

    foreach ($productIds as $productId) {
        expect(wapRulePriceRows($productId))->toBeEmpty();
        expect(wapDiscountRows($productId))->toBeEmpty();
        expect(wapRuntimeFpt($productId))->toBe(10.0);
    }
});

it('removes the FPT discount of a product after a row refresh no longer matches it', function (): void {
    $fixture = wapCreateProduct('WAP-ROW-' . uniqid(), $this->wapAttributeCode);
    $product = $fixture['product'];
    $productId = (int) $product->getId();
    $GLOBALS['wap_cleanup']['categories'][] = (int) $fixture['category']->getId();
    $GLOBALS['wap_cleanup']['products'][] = $productId;
    $rule = wapCreateRule($product);
    $GLOBALS['wap_cleanup']['rules'][] = (int) $rule->getId();
    wapApplyAllRules();
    expect(wapDiscountRows($productId))->toHaveCount(1);
    expect(wapRulePriceRows($productId))->not->toBeEmpty();

    Mage::app()->getArea('adminhtml')->load(Mage_Core_Model_App_Area::PART_EVENTS);
    $product->setSku('WAP-ROW-NO-MATCH-' . uniqid())->setStockData(null)->save();

    expect(wapRulePriceRows($productId))->toBeEmpty();
    expect(wapDiscountRows($productId))->toBeEmpty();
    expect(wapRuntimeFpt($productId))->toBe(10.0);
});

it('adds the FPT discount of a product after a row refresh starts to match it', function (): void {
    $fixture = wapCreateProduct('WAP-ROW-ADD-' . uniqid(), $this->wapAttributeCode);
    $product = $fixture['product'];
    $productId = (int) $product->getId();
    $GLOBALS['wap_cleanup']['categories'][] = (int) $fixture['category']->getId();
    $GLOBALS['wap_cleanup']['products'][] = $productId;
    $matchedSku = 'WAP-ROW-ADD-MATCH-' . uniqid();
    $rule = wapCreateRule($product, matchedSku: $matchedSku);
    $GLOBALS['wap_cleanup']['rules'][] = (int) $rule->getId();
    wapApplyAllRules();
    expect(wapDiscountRows($productId))->toBeEmpty();

    Mage::app()->getArea('adminhtml')->load(Mage_Core_Model_App_Area::PART_EVENTS);
    $product->setSku($matchedSku)->setStockData(null)->save();

    $rows = wapDiscountRows($productId);
    expect($rows)->toHaveCount(1);
    expect((float) $rows[0]['value'])->toBe(90.0);
    expect(wapRuntimeFpt($productId))->toBe(9.0);
});

it('rebuilds website and customer group rows without touching unrelated products', function (): void {
    $moving = wapCreateProduct('WAP-MOVE-' . uniqid(), $this->wapAttributeCode, [1, 2]);
    $unrelated = wapCreateProduct('WAP-MOVE-UNRELATED-' . uniqid(), $this->wapAttributeCode);
    $movingId = (int) $moving['product']->getId();
    $unrelatedId = (int) $unrelated['product']->getId();
    foreach ([$moving, $unrelated] as $fixture) {
        $GLOBALS['wap_cleanup']['categories'][] = (int) $fixture['category']->getId();
        $GLOBALS['wap_cleanup']['products'][] = (int) $fixture['product']->getId();
    }
    $rule = wapCreateRule($moving['product'], 10, 0, false, [1, 2], [0, 1]);
    $GLOBALS['wap_cleanup']['rules'][] = (int) $rule->getId();

    wapApplyAllRules();

    $rows = wapDiscountRows($movingId);
    $pairs = array_map(static fn(array $row): string => $row['website_id'] . ':' . $row['customer_group_id'], $rows);
    sort($pairs);
    expect($pairs)->toBe(['1:0', '1:1', '2:0', '2:1']);
    expect(wapDiscountRows($unrelatedId))->toBeEmpty();

    $rule->setWebsiteIds([2])->setCustomerGroupIds([1])->save();
    wapApplyAllRules();

    $rows = wapDiscountRows($movingId);
    expect($rows)->toHaveCount(1);
    expect((int) $rows[0]['website_id'])->toBe(2);
    expect((int) $rows[0]['customer_group_id'])->toBe(1);
    expect((float) $rows[0]['value'])->toBe(90.0);
    expect(wapDiscountRows($unrelatedId))->toBeEmpty();
});
