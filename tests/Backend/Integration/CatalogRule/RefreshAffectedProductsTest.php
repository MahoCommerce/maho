<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

final class Wap_AffectedProductProbe
{
    /** @var list<array<string, mixed>> */
    public static array $records = [];

    public function capture(\Maho\Event\Observer $observer): void
    {
        $event = $observer->getEvent();
        $condition = $event->getProductCondition();
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        self::$records[] = [
            'event_product' => $event->getProduct(),
            'condition_class' => $condition::class,
            'condition_sql' => (string) $condition->getIdsSelect($read),
            'ids' => array_map(intval(...), $read->fetchCol($condition->getIdsSelect($read))),
        ];
    }
}

function wapCreateFeeAttribute(string $code): void
{
    Mage::getResourceModel('catalog/setup', 'catalog_setup')->addAttribute(Mage_Catalog_Model_Product::ENTITY, $code, [
        'type' => 'static',
        'backend' => Mage_Weee_Model_Attribute_Backend_Weee_Tax::getBackendModelName(),
        'input' => 'weee',
        'label' => 'WAP fixed tax',
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
        'required' => false,
        'user_defined' => true,
        'group' => 'General',
    ]);
    Mage::getSingleton('eav/config')->clear();
}

function wapCreateProduct(string $sku, string $attributeCode, array $websiteIds = [1]): array
{
    $store = Mage::app()->getStore(1);
    $root = Mage::getModel('catalog/category')->setStoreId(0)->load($store->getRootCategoryId());
    $category = Mage::getModel('catalog/category')->setStoreId(0)->setName('WAP ' . $sku)
        ->setUrlKey(strtolower($sku))->setIsActive(1);
    $category->setAttributeSetId($category->getDefaultAttributeSetId())->setPath($root->getPath())->save();

    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku($sku)
        ->setName('WAP ' . $sku)
        ->setPrice(80.0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)
        ->setWebsiteIds($websiteIds)
        ->setCategoryIds([(int) $category->getId()])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 20]);
    $country = (string) Mage::getModel('tax/calculation')->getRateRequest()->getCountryId();
    $fees = [];
    foreach ($websiteIds as $websiteId) {
        $fees[] = [
            'website_id' => $websiteId,
            'country' => $country,
            'state' => '*',
            'price' => 10.0,
        ];
    }
    $product->setData($attributeCode, $fees);
    $product->save();

    return ['category' => $category, 'product' => $product, 'country' => $country];
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
    $matchedSku ??= (string) $product->getSku();
    $rule = Mage::getModel('catalogrule/rule')->setName('WAP 10 ' . uniqid())
        ->setIsActive(true)
        ->setFromDate(date('Y-m-d', time() - 86400))
        ->setToDate(date('Y-m-d', time() + 86400))
        ->setWebsiteIds($websiteIds)
        ->setCustomerGroupIds($customerGroupIds)
        ->setSortOrder($sortOrder)
        ->setSimpleAction($action)
        ->setDiscountAmount($amount)
        ->setStopRulesProcessing($stop);
    $rule->loadPost(['conditions' => [
        '1' => ['type' => 'catalogrule/rule_condition_combine', 'aggregator' => 'all', 'value' => '1', 'new_child' => ''],
        '1--1' => ['type' => 'catalogrule/rule_condition_product', 'attribute' => 'sku', 'operator' => '==', 'value' => $matchedSku],
    ]]);
    $rule->save();
    return $rule;
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

function wapEventIds(): array
{
    $ids = [];
    foreach (Wap_AffectedProductProbe::$records as $record) {
        $ids = array_merge($ids, $record['ids']);
    }
    return array_values(array_unique(array_map(intval(...), $ids)));
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
    Wap_AffectedProductProbe::$records = [];
    Mage::getConfig()->setNode('global/events/catalogrule_after_apply/observers/wap_probe/class', Wap_AffectedProductProbe::class);
    Mage::getConfig()->setNode('global/events/catalogrule_after_apply/observers/wap_probe/method', 'capture');
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
    foreach (array_reverse($GLOBALS['wap_cleanup']['rules']) as $ruleId) {
        try {
            Mage::getModel('catalogrule/rule')->load($ruleId)->delete();
        } catch (Throwable) {
        }
    }
    foreach (array_reverse($GLOBALS['wap_cleanup']['products']) as $productId) {
        try {
            Mage::getModel('catalog/product')->load($productId)->delete();
        } catch (Throwable) {
        }
    }
    foreach (array_reverse($GLOBALS['wap_cleanup']['categories']) as $categoryId) {
        try {
            Mage::getModel('catalog/category')->load($categoryId)->delete();
        } catch (Throwable) {
        }
    }
    if ($GLOBALS['wap_cleanup']['attribute']) {
        try {
            Mage::getModel('catalog/resource_eav_attribute')
                ->loadByCode(Mage_Catalog_Model_Product::ENTITY, $GLOBALS['wap_cleanup']['attribute'])
                ->delete();
            Mage::getSingleton('eav/config')->clear();
        } catch (Throwable) {
        }
    }
    foreach ($this->wapPreviousConfig ?? [] as $storeId => $values) {
        foreach ($values as $path => $value) {
            Mage::app()->getStore((int) $storeId)->setConfig($path, $value);
        }
    }
    Mage::app()->setCurrentStore($this->wapPreviousStore ?? Mage_Core_Model_App::ADMIN_STORE_ID);
});

it('refreshes native FPT discounts from affected product IDs', function (): void {
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
    $discountTable = $resources->getTableName('weee/discount');
    $rulePrice = $read->fetchRow(
        "SELECT rule_price FROM {$rulePriceTable} WHERE product_id = ? AND website_id = ? AND customer_group_id = ? AND rule_date = ?",
        [$productId, 1, 0, Mage::app()->getLocale()->formatDateForDb(time(), withTime: false)],
    );
    $priceIndex = $read->fetchRow(
        "SELECT final_price, min_price FROM {$priceIndexTable} WHERE entity_id = ? AND website_id = ? AND customer_group_id = ?",
        [$productId, 1, 0],
    );
    $loaded = Mage::getModel('catalog/product')->setStoreId(1)->load($productId)->setCustomerGroupId(0);
    $runtimeFpt = (float) Mage::getModel('weee/tax')->getWeeeAmount($loaded, null, null, 1, false, false);
    $discountRows = $read->fetchAll(
        "SELECT entity_id, website_id, customer_group_id, value FROM {$discountTable} WHERE entity_id = ?",
        [$productId],
    );
    $probe = Wap_AffectedProductProbe::$records;
    expect($runtimeFpt)->toBe(9.0);
    expect((float) ($rulePrice['rule_price'] ?? 0))->toBe(72.0);
    expect((float) ($priceIndex['min_price'] ?? 0))->toBe(72.0);
    expect($probe)->not->toBeEmpty();
    expect(array_last($probe)['ids'])->toContain($productId);
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

    Wap_AffectedProductProbe::$records = [];
    $rule->setDiscountAmount(20)->save();
    wapApplyAllRules();

    expect(wapEventIds())->toContain($productId);
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
    expect(wapEventIds())->toContain($stackedId, $stoppedId, $fixedId)->not->toContain($unrelatedId);
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

    expect(wapEventIds())->toContain($productId);
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
    Wap_AffectedProductProbe::$records = [];
    wapApplyAllRules();

    expect(wapEventIds())->toContain(...$productIds);
    foreach ($productIds as $productId) {
        expect(wapRulePriceRows($productId))->toBeEmpty();
        expect(wapDiscountRows($productId))->toBeEmpty();
        expect(wapRuntimeFpt($productId))->toBe(10.0);
    }
});

it('includes a row refresh product after its old rule prices are gone', function (): void {
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
    Wap_AffectedProductProbe::$records = [];
    $product->setSku('WAP-ROW-NO-MATCH-' . uniqid())->setStockData(null)->save();

    expect(wapRulePriceRows($productId))->toBeEmpty();
    expect(wapEventIds())->toContain($productId);
    expect(wapDiscountRows($productId))->toBeEmpty();
    expect(wapRuntimeFpt($productId))->toBe(10.0);
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
    Wap_AffectedProductProbe::$records = [];
    wapApplyAllRules();

    $rows = wapDiscountRows($movingId);
    expect($rows)->toHaveCount(1);
    expect((int) $rows[0]['website_id'])->toBe(2);
    expect((int) $rows[0]['customer_group_id'])->toBe(1);
    expect((float) $rows[0]['value'])->toBe(90.0);
    expect(wapEventIds())->toContain($movingId)->not->toContain($unrelatedId);
    expect(wapDiscountRows($unrelatedId))->toBeEmpty();
});
