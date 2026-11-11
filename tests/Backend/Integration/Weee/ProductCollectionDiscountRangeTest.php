<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function widcCreateFeeAttribute(string $code): void
{
    Mage::getResourceModel('catalog/setup', 'catalog_setup')->addAttribute(Mage_Catalog_Model_Product::ENTITY, $code, [
        'type' => 'static',
        'backend' => Mage_Weee_Model_Attribute_Backend_Weee_Tax::getBackendModelName(),
        'input' => 'weee',
        'label' => 'WIDC fixed tax',
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
        'required' => false,
        'user_defined' => true,
        'group' => 'General',
    ]);
    Mage::getSingleton('eav/config')->clear();
}

function widcCreateProduct(string $sku, string $attributeCode, float $price = 80.0, float $priceRange = 10.0): array
{
    $store = Mage::app()->getStore(1);
    $root = Mage::getModel('catalog/category')->setStoreId(0)->load($store->getRootCategoryId());
    $category = Mage::getModel('catalog/category')->setStoreId(0)->setName('WIDC ' . $sku)
        ->setUrlKey(strtolower($sku))->setIsActive(1)->setData('filter_price_range', $priceRange);
    $category->setAttributeSetId($category->getDefaultAttributeSetId())->setPath($root->getPath())->save();

    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku($sku)
        ->setName('WIDC ' . $sku)
        ->setPrice($price)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)
        ->setWebsiteIds([1])
        ->setCategoryIds([(int) $category->getId()])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 20]);
    $country = (string) Mage::getModel('tax/calculation')->getRateRequest()->getCountryId();
    $product->setData($attributeCode, [[
        'website_id' => 1,
        'country' => $country,
        'state' => '*',
        'price' => 10.0,
    ]]);
    $product->save();

    return ['category' => $category, 'product' => $product, 'country' => $country];
}

function widcCreateRule(Mage_Catalog_Model_Product $product, string $name, string $action, float $amount, int $sortOrder = 0): Mage_CatalogRule_Model_Rule
{
    $rule = Mage::getModel('catalogrule/rule')->setName($name)
        ->setIsActive(true)
        ->setFromDate(date('Y-m-d', time() - 86400))
        ->setToDate(date('Y-m-d', time() + 86400))
        ->setWebsiteIds([1])
        ->setCustomerGroupIds([0])
        ->setSortOrder($sortOrder)
        ->setSimpleAction($action)
        ->setDiscountAmount($amount)
        ->setStopRulesProcessing(false);
    $rule->loadPost(['conditions' => [
        '1' => ['type' => 'catalogrule/rule_condition_combine', 'aggregator' => 'all', 'value' => '1', 'new_child' => ''],
        '1--1' => ['type' => 'catalogrule/rule_condition_product', 'attribute' => 'sku', 'operator' => '==', 'value' => (string) $product->getSku()],
    ]]);
    $rule->save();
    // Use the native full rule refresh so catalogrule_after_apply runs.
    $rule->applyAll();
    return $rule;
}

function widcRangeCounts(int $categoryId, int $range, int $productId): array
{
    $category = Mage::getModel('catalog/category')->setStoreId(1)->load($categoryId);
    $layer = Mage::getModel('catalog/layer')->setCurrentCategory($category);
    $collection = $layer->getProductCollection();
    $collection->addPriceData(0, 1);
    expect(array_map(intval(...), $collection->getAllIds()))->toContain($productId);
    $filter = Mage::getModel('catalog/layer_filter_price')
        ->setLayer($layer)
        ->setWebsiteId(1)
        ->setCustomerGroupId(0);
    // Build the public filter items used by category layered navigation.
    $items = $filter->getItems();
    expect($items)->not->toBeEmpty();

    $counts = $filter->getRangeItemCounts($range);
    $counts = array_map(intval(...), $counts);
    ksort($counts);
    expect(array_sum(array_map(static fn($item): int => (int) $item->getCount(), $items)))->toBe(array_sum($counts));

    return $counts;
}

function widcExpectedBucket(int $productId, float $weeeAmount, int $range, bool $displayWeee = true): int
{
    $read = Mage::getSingleton('core/resource')->getConnection('core_read');
    $table = Mage::getSingleton('core/resource')->getTableName('catalog/product_index_price');
    $index = $read->fetchRow(
        "SELECT min_price FROM {$table} WHERE entity_id = ? AND website_id = ? AND customer_group_id = ?",
        [$productId, 1, 0],
    );
    expect($index)->not->toBeEmpty();
    $store = Mage::app()->getStore(1);
    $currencyRate = (float) $store->getCurrentCurrencyRate();
    $price = round(((float) $index['min_price'] + ($displayWeee ? $weeeAmount : 0.0)) * $currencyRate, 2);
    return (int) floor($price / $range) + 1;
}

beforeEach(function (): void {
    $GLOBALS['widc_cleanup'] = ['products' => [], 'categories' => [], 'rules' => [], 'attribute' => null];
    $this->widcPreviousStore = Mage::app()->getStore()->getId();
    Mage::app()->setCurrentStore(1);
    Mage::app()->getArea('global')->load(Mage_Core_Model_App_Area::PART_EVENTS);
    $token = substr(hash('sha256', uniqid('', true)), 0, 8);
    $this->widcAttributeCode = 'widc_fee_' . $token;
    $GLOBALS['widc_cleanup']['attribute'] = $this->widcAttributeCode;
    widcCreateFeeAttribute($this->widcAttributeCode);
    $this->widcStore = Mage::app()->getStore(1);
    $this->widcPreviousConfig = [];
    foreach (['tax/weee/enable', 'tax/weee/discount', 'tax/weee/display_list'] as $path) {
        $this->widcPreviousConfig[$path] = $this->widcStore->getConfig($path);
    }
    $this->widcStore->setConfig('tax/weee/enable', '1');
    $this->widcStore->setConfig('tax/weee/discount', '1');
    $this->widcStore->setConfig('tax/weee/display_list', Mage_Weee_Model_Tax::DISPLAY_INCL);
});

afterEach(function (): void {
    foreach (array_reverse($GLOBALS['widc_cleanup']['rules']) as $ruleId) {
        try {
            Mage::getModel('catalogrule/rule')->load($ruleId)->delete();
        } catch (Throwable) {
        }
    }
    foreach (array_reverse($GLOBALS['widc_cleanup']['products']) as $productId) {
        try {
            Mage::getModel('catalog/product')->load($productId)->delete();
        } catch (Throwable) {
        }
    }
    foreach (array_reverse($GLOBALS['widc_cleanup']['categories']) as $categoryId) {
        try {
            Mage::getModel('catalog/category')->load($categoryId)->delete();
        } catch (Throwable) {
        }
    }
    if ($GLOBALS['widc_cleanup']['attribute']) {
        try {
            Mage::getModel('catalog/resource_eav_attribute')
                ->loadByCode(Mage_Catalog_Model_Product::ENTITY, $GLOBALS['widc_cleanup']['attribute'])
                ->delete();
            Mage::getSingleton('eav/config')->clear();
        } catch (Throwable) {
        }
    }
    foreach ($this->widcPreviousConfig ?? [] as $path => $value) {
        Mage::app()->getStore(1)->setConfig($path, $value);
    }
    Mage::app()->setCurrentStore($this->widcPreviousStore ?? Mage_Core_Model_App::ADMIN_STORE_ID);
});

it('uses the remaining FPT percentage in the real catalog price range for a ten percent rule', function (): void {
    $fixture = widcCreateProduct('WIDC-10-' . uniqid(), $this->widcAttributeCode, 80.0, 1.0);
    $product = $fixture['product'];
    $GLOBALS['widc_cleanup']['categories'][] = (int) $fixture['category']->getId();
    $GLOBALS['widc_cleanup']['products'][] = (int) $product->getId();
    $rule = widcCreateRule($product, 'WIDC 10 ' . uniqid(), 'by_percent', 10);
    $GLOBALS['widc_cleanup']['rules'][] = (int) $rule->getId();

    $read = Mage::getSingleton('core/resource')->getConnection('core_read');
    $discountTable = Mage::getSingleton('core/resource')->getTableName('weee/discount');
    $discount = $read->fetchRow(
        "SELECT value FROM {$discountTable} WHERE entity_id = ? AND website_id = ? AND customer_group_id = ?",
        [(int) $product->getId(), 1, 0],
    );
    expect($discount)->not->toBeFalse();
    expect((float) $discount['value'])->toBe(90.0);

    $loaded = Mage::getModel('catalog/product')->setStoreId(1)->load((int) $product->getId())->setCustomerGroupId(0);
    $runtimeAmount = (float) Mage::getModel('weee/tax')->getWeeeAmount($loaded, null, null, 1, false, false);
    expect($runtimeAmount)->toBe(9.0);

    $expectedBucket = widcExpectedBucket((int) $product->getId(), $runtimeAmount, 1);
    $counts = widcRangeCounts((int) $fixture['category']->getId(), 1, (int) $product->getId());
    expect($counts)->toBe([$expectedBucket => 1]);
});

it('keeps zero and one hundred percent rules aligned with runtime behavior', function (float $discountAmount, float $stored, float $runtimeAmount): void {
    $fixture = widcCreateProduct('WIDC-EDGE-' . uniqid(), $this->widcAttributeCode);
    $product = $fixture['product'];
    $GLOBALS['widc_cleanup']['categories'][] = (int) $fixture['category']->getId();
    $GLOBALS['widc_cleanup']['products'][] = (int) $product->getId();
    $rule = widcCreateRule($product, 'WIDC edge ' . uniqid(), 'by_percent', $discountAmount);
    $GLOBALS['widc_cleanup']['rules'][] = (int) $rule->getId();

    $read = Mage::getSingleton('core/resource')->getConnection('core_read');
    $discountTable = Mage::getSingleton('core/resource')->getTableName('weee/discount');
    $storedValue = $read->fetchRow(
        "SELECT value FROM {$discountTable} WHERE entity_id = ? AND website_id = ? AND customer_group_id = ?",
        [(int) $product->getId(), 1, 0],
    );
    expect($storedValue)->not->toBeFalse();
    expect((float) $storedValue['value'])->toBe($stored);
    $loaded = Mage::getModel('catalog/product')->setStoreId(1)->load((int) $product->getId())->setCustomerGroupId(0);
    $actualRuntime = (float) Mage::getModel('weee/tax')->getWeeeAmount($loaded, null, null, 1, false, false);
    expect($actualRuntime)->toBe($runtimeAmount);
    $bucket = widcExpectedBucket((int) $product->getId(), $runtimeAmount, 10);
    $counts = widcRangeCounts((int) $fixture['category']->getId(), 10, (int) $product->getId());
    expect($counts)->toBe([$bucket => 1]);
})->with([
    'zero percent leaves FPT unchanged' => [0.0, 100.0, 10.0],
    // Runtime treats the stored zero as its current no-discount sentinel. Preserve that behavior;
    // this case does not assert that 100% catalog-rule discounts should discount FPT.
    'one hundred percent follows current runtime sentinel behavior' => [100.0, 0.0, 10.0],
]);

it('leaves FPT undiscounted for a fixed amount catalog rule with no percentage row', function (): void {
    $fixture = widcCreateProduct('WIDC-FIXED-' . uniqid(), $this->widcAttributeCode);
    $product = $fixture['product'];
    $GLOBALS['widc_cleanup']['categories'][] = (int) $fixture['category']->getId();
    $GLOBALS['widc_cleanup']['products'][] = (int) $product->getId();
    $rule = widcCreateRule($product, 'WIDC fixed ' . uniqid(), 'by_fixed', 5);
    $GLOBALS['widc_cleanup']['rules'][] = (int) $rule->getId();

    $read = Mage::getSingleton('core/resource')->getConnection('core_read');
    $discountTable = Mage::getSingleton('core/resource')->getTableName('weee/discount');
    $storedValue = $read->fetchRow(
        "SELECT value FROM {$discountTable} WHERE entity_id = ? AND website_id = ? AND customer_group_id = ?",
        [(int) $product->getId(), 1, 0],
    );
    expect($storedValue)->toBeFalse();
    $loaded = Mage::getModel('catalog/product')->setStoreId(1)->load((int) $product->getId())->setCustomerGroupId(0);
    $runtimeAmount = (float) Mage::getModel('weee/tax')->getWeeeAmount($loaded, null, null, 1, false, false);
    expect($runtimeAmount)->toBe(10.0);
    $bucket = widcExpectedBucket((int) $product->getId(), $runtimeAmount, 10);
    expect(widcRangeCounts((int) $fixture['category']->getId(), 10, (int) $product->getId()))->toBe([$bucket => 1]);
});

it('keeps catalog price ranges aligned when stacked percentage rules leave a fractional remainder', function (): void {
    $fixture = widcCreateProduct('WIDC-STACK-' . uniqid(), $this->widcAttributeCode);
    $product = $fixture['product'];
    $GLOBALS['widc_cleanup']['categories'][] = (int) $fixture['category']->getId();
    $GLOBALS['widc_cleanup']['products'][] = (int) $product->getId();
    foreach ([33.0, 10.0] as $index => $amount) {
        $rule = widcCreateRule($product, 'WIDC stack ' . $index . ' ' . uniqid(), 'by_percent', $amount, $index);
        $GLOBALS['widc_cleanup']['rules'][] = (int) $rule->getId();
    }

    $read = Mage::getSingleton('core/resource')->getConnection('core_read');
    $discountTable = Mage::getSingleton('core/resource')->getTableName('weee/discount');
    $storedRow = $read->fetchRow(
        "SELECT value FROM {$discountTable} WHERE entity_id = ? AND website_id = ? AND customer_group_id = ?",
        [(int) $product->getId(), 1, 0],
    );
    expect($storedRow)->not->toBeFalse();
    $stored = (float) $storedRow['value'];
    expect($stored)->toBe(60.3);
    $loaded = Mage::getModel('catalog/product')->setStoreId(1)->load((int) $product->getId())->setCustomerGroupId(0);
    $runtimeAmount = (float) Mage::getModel('weee/tax')->getWeeeAmount($loaded, null, null, 1, false, false);
    expect($runtimeAmount)->toBe(6.0);
    $bucket = widcExpectedBucket((int) $product->getId(), $runtimeAmount, 1);
    expect(widcRangeCounts((int) $fixture['category']->getId(), 1, (int) $product->getId()))->toBe([$bucket => 1]);
});

it('gates range FPT by the existing WEEE and list display settings', function (string $setting, mixed $value, bool $includeFpt, bool $enabled, bool $discounted): void {
    $fixture = widcCreateProduct('WIDC-GATE-' . uniqid(), $this->widcAttributeCode);
    $product = $fixture['product'];
    $GLOBALS['widc_cleanup']['categories'][] = (int) $fixture['category']->getId();
    $GLOBALS['widc_cleanup']['products'][] = (int) $product->getId();
    $rule = widcCreateRule($product, 'WIDC gate ' . uniqid(), 'by_percent', 10);
    $GLOBALS['widc_cleanup']['rules'][] = (int) $rule->getId();
    Mage::app()->getStore(1)->setConfig($setting, (string) $value);

    $loaded = Mage::getModel('catalog/product')->setStoreId(1)->load((int) $product->getId())->setCustomerGroupId(0);
    $runtimeAmount = (float) Mage::getModel('weee/tax')->getWeeeAmount($loaded, null, null, 1, false, false);
    expect((bool) Mage::helper('weee')->isEnabled(1))->toBe($enabled);
    expect((bool) Mage::helper('weee')->isDiscounted(1))->toBe($discounted);
    $expectedRuntime = $enabled ? ($discounted ? 9.0 : 10.0) : 0.0;
    expect($runtimeAmount)->toBe($expectedRuntime);

    $bucket = widcExpectedBucket((int) $product->getId(), $runtimeAmount, 10, $includeFpt);
    expect(widcRangeCounts((int) $fixture['category']->getId(), 10, (int) $product->getId()))->toBe([$bucket => 1]);
})->with([
    'WEEE disabled' => ['tax/weee/enable', '0', false, false, true],
    'FPT discounts disabled' => ['tax/weee/discount', '0', true, true, false],
    'list display excludes FPT' => ['tax/weee/display_list', Mage_Weee_Model_Tax::DISPLAY_EXCL, false, true, true],
]);
