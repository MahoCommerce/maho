<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function createPreloadRuleProduct(array $websiteIds): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product')->setStoreId(0);
    return $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId($product->getDefaultAttributeSetId())
        ->setSku('catalog-preload-' . uniqid())
        ->setName('Catalog rule test product')
        ->setPrice(100.0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)
        ->setWebsiteIds($websiteIds)
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100])
        ->save();
}

it('keeps catalog prices when a cart changes date, website or customer group', function (): void {
    $app = Mage::app();
    $siteCode = 'catalog_rule_preload';
    $otherWebsite = createPriceWebsite($siteCode);
    $store = $app->getStore(1);
    $otherStore = $app->getStore($siteCode);
    $previousStore = $app->getStore();
    $locale = $app->getLocale();
    $clockProperty = new ReflectionProperty($app, '_locale');
    $clock = $this->createPartialMock(Mage_Core_Model_Locale::class, ['utcToStore']);
    $now = new DateTimeImmutable('2026-11-23 07:59:59 UTC');
    $clock->method('utcToStore')->willReturnCallback(function (
        mixed $store = null,
        string|int|DateTimeInterface|null $date = null,
    ) use ($locale, &$now): DateTimeImmutable {
        return $locale->utcToStore($store, $date ?? $now);
    });
    $clockProperty->setValue($app, $clock);
    $app->loadAreaPart(Mage_Core_Model_App_Area::AREA_FRONTEND, Mage_Core_Model_App_Area::PART_EVENTS);
    foreach ([$store, $otherStore] as $view) {
        $view->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, 'America/Los_Angeles');
        $view->setConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_ALLOW, 'USD');
        $view->setConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_DEFAULT, 'USD');
    }

    $product = createPreloadRuleProduct([(int) $store->getWebsiteId(), (int) $otherWebsite->getId()]);
    $productId = (int) $product->getId();
    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $priceTable = $resource->getTableName('catalogrule/rule_product_price');
    $cases = [
        [$store, 0, '2026-11-23 07:59:59 UTC', '2026-11-22', 90.0],
        [$store, 1, '2026-11-23 07:59:59 UTC', '2026-11-22', 75.0],
        [$otherStore, 1, '2026-11-23 07:59:59 UTC', '2026-11-22', 65.0],
        [$otherStore, 1, '2026-11-23 08:00:01 UTC', '2026-11-23', 80.0],
    ];

    try {
        foreach ($cases as [$view, $groupId, $timestamp, $day, $price]) {
            $write->insert($priceTable, [
                'product_id' => $productId,
                'website_id' => (int) $view->getWebsiteId(),
                'customer_group_id' => $groupId,
                'rule_date' => $day,
                'rule_price' => $price,
            ]);
        }
        $app->setCurrentStore($store);
        $storeId = (int) $store->getId();
        $quote = Mage::getModel('sales/quote')->setStoreId($storeId)->setCustomerGroupId(0);
        $quote->addProduct(Mage::getModel('catalog/product')->setStoreId($storeId)->load($productId), 1);
        $quote->getShippingAddress();
        $item = $quote->getAllVisibleItems()[0];
        $prices = [];
        foreach ($cases as [$view, $groupId, $timestamp, $day, $price]) {
            $now = new DateTimeImmutable($timestamp);
            $app->setCurrentStore($view);
            $storeId = (int) $view->getId();
            $quote->setStoreId($storeId)->setCustomerGroupId($groupId);
            $item->setProduct(Mage::getModel('catalog/product')->setStoreId($storeId)->load($productId));
            $quote->setTotalsCollectedFlag(false)->collectTotals();

            $freshProduct = Mage::getModel('catalog/product')->setStoreId($storeId)->load($productId);
            $freshProduct->setCustomerGroupId($groupId);
            $prices[] = [(float) $item->getPrice(), (float) $freshProduct->getFinalPrice(1)];
        }

        expect($prices)->toBe([[90.0, 90.0], [75.0, 75.0], [65.0, 65.0], [80.0, 80.0]]);
    } finally {
        $clockProperty->setValue($app, $locale);
        $app->setCurrentStore($previousStore);
        $product->delete();
        deletePriceWebsite($siteCode);
    }
});

it('preloads only the missing prices in the current context', function (): void {
    $resource = new class extends Mage_CatalogRule_Model_Resource_Rule {
        public array $calls = [];

        #[\Override]
        public function getRulePrices($date, $websiteId, $customerGroupId, $productIds)
        {
            $this->calls[] = [(int) $customerGroupId, $productIds];
            return in_array(42, $productIds, true) ? [42 => 90.0] : [];
        }
    };
    Mage::register('_resource_singleton/catalogrule/rule', $resource, true);
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setCustomerGroupId(0);
    $quote->addItem(Mage::getModel('sales/quote_item')->setProductId(42));
    $observer = new Maho\Event\Observer(['quote' => $quote]);
    $catalogRuleObserver = new Mage_CatalogRule_Model_Observer();

    try {
        $catalogRuleObserver->preloadPriceRules($observer);
        $quote->setCustomerGroupId(1);
        $catalogRuleObserver->preloadPriceRules($observer);
        $quote->addItem(Mage::getModel('sales/quote_item')->setProductId(43));
        $catalogRuleObserver->preloadPriceRules($observer);
        $catalogRuleObserver->preloadPriceRules($observer);
        expect($resource->calls)->toBe([[0, [42]], [1, [42]], [1, [43]]]);
    } finally {
        Mage::unregister('_resource_singleton/catalogrule/rule');
    }
});

it('refreshes missing and cached cart prices when catalog rules are applied', function (string $area): void {
    $app = Mage::app();
    $store = $app->getStore(1);
    $storeId = (int) $store->getId();
    $previousStore = $app->getStore();
    $locale = $app->getLocale();
    $clockProperty = new ReflectionProperty($app, '_locale');
    $clock = $this->createPartialMock(Mage_Core_Model_Locale::class, ['utcToStore']);
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $clock->method('utcToStore')->willReturnCallback(static fn(
        mixed $store = null,
        string|int|DateTimeInterface|null $date = null,
    ): DateTimeImmutable => $locale->utcToStore($store, $date ?? $now));
    $clockProperty->setValue($app, $clock);
    $app->loadAreaPart(Mage_Core_Model_App_Area::AREA_FRONTEND, Mage_Core_Model_App_Area::PART_EVENTS);
    $app->loadAreaPart($area, Mage_Core_Model_App_Area::PART_EVENTS);
    $product = createPreloadRuleProduct([(int) $store->getWebsiteId()]);
    $productId = (int) $product->getId();
    $rule = Mage::getModel('catalogrule/rule')->setName('Catalog rule refresh test')
        ->setWebsiteIds([(int) $store->getWebsiteId()])->setCustomerGroupIds([0])
        ->setIsActive(true)->setSimpleAction('by_percent');
    $rule->loadPost(['conditions' => [
        '1' => ['type' => 'catalogrule/rule_condition_combine', 'aggregator' => 'all', 'value' => '1', 'new_child' => ''],
        '1--1' => ['type' => 'catalogrule/rule_condition_product', 'attribute' => 'sku', 'operator' => '==', 'value' => $product->getSku()],
    ]]);
    $read = static function () use ($app, $store, $storeId, $productId): float {
        $app->setCurrentStore($store);
        $quote = Mage::getModel('sales/quote')->setStoreId($storeId)->setCustomerGroupId(0);
        $freshProduct = Mage::getModel('catalog/product')->setStoreId($storeId)->load($productId);
        $quote->addProduct($freshProduct, 1);
        $quote->getShippingAddress();
        $quote->collectTotals();
        return (float) $quote->getAllVisibleItems()[0]->getPrice();
    };

    try {
        $prices = [$read()];
        foreach ([20, 30] as $discount) {
            $app->setCurrentStore(Mage_Core_Model_Store::ADMIN_CODE);
            $rule->setDiscountAmount($discount)->save();
            if ($area === 'crontab') {
                Mage::getSingleton('catalogrule/observer')->dailyCatalogUpdate(new Maho\Event\Observer());
            } else {
                Mage::getModel('catalogrule/rule')->applyAll();
            }
            $prices[] = $read();
        }
        expect($prices)->toBe([100.0, 80.0, 70.0]);
    } finally {
        $clockProperty->setValue($app, $locale);
        $app->setCurrentStore($previousStore);
        if ($rule->getId()) {
            $rule->delete();
        }
        $product->delete();
    }
})->with([
    'admin' => [Mage_Core_Model_App_Area::AREA_ADMINHTML],
    'cron' => ['crontab'],
]);
