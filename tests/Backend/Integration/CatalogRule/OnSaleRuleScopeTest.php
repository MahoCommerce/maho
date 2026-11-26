<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('lists products for the selected On Sale rule only while it applies in the storefront', function (): void {
    $app = Mage::app();
    $siteCode = 'catalog_rule_onsale';
    $otherWebsite = createPriceWebsite($siteCode);
    $store = $app->getStore(1);
    $otherStore = $app->getStore($siteCode);
    $previousStore = $app->getStore();
    $locale = $app->getLocale();
    $clockProperty = new ReflectionProperty($app, '_locale');
    $clock = $this->createPartialMock(Mage_Core_Model_Locale::class, ['utcToStore']);
    $now = new DateTimeImmutable('2026-11-22 08:00:00 UTC');
    $clock->method('utcToStore')->willReturnCallback(function (
        mixed $store = null,
        string|int|DateTimeInterface|null $date = null,
    ) use ($locale, &$now): DateTimeImmutable {
        return $locale->utcToStore($store, $date ?? $now);
    });
    $clockProperty->setValue($app, $clock);
    $app->loadAreaPart(Mage_Core_Model_App_Area::AREA_FRONTEND, Mage_Core_Model_App_Area::PART_EVENTS);
    Mage::getDesign()->setArea(Mage_Core_Model_App_Area::AREA_FRONTEND);
    foreach ([$store, $otherStore] as $view) {
        $view->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, 'America/Los_Angeles');
        $view->setConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_ALLOW, 'USD');
        $view->setConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_DEFAULT, 'USD');
    }

    $firstWebsiteId = (int) $store->getWebsiteId();
    $product = Mage::getModel('catalog/product')->setStoreId(0);
    $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId($product->getDefaultAttributeSetId())
        ->setSku('catalog-onsale-' . uniqid())
        ->setName('On Sale test product')
        ->setPrice(100.0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)
        ->setWebsiteIds([$firstWebsiteId, (int) $otherWebsite->getId()])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100])
        ->save();
    $productId = (int) $product->getId();
    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $rule = Mage::getModel('catalogrule/rule')->setName('On Sale test rule')
        ->setIsActive(true)
        ->setWebsiteIds([$firstWebsiteId])
        ->setCustomerGroupIds([0])
        ->setSimpleAction('by_percent')
        ->setDiscountAmount(10)
        ->save();
    $ruleId = (int) $rule->getId();
    $priceTable = $resource->getTableName('catalogrule/rule_product_price');
    $cases = [
        [$store, 0, '2026-11-22 07:59:59 UTC', '2026-11-21', 95.0],
        [$store, 0, '2026-11-22 08:00:00 UTC', '2026-11-22', 90.0],
        [$store, 0, '2026-11-23 07:59:59 UTC', '2026-11-22', 90.0],
        [$store, 0, '2026-11-23 08:00:00 UTC', '2026-11-23', 95.0],
        [$store, 1, '2026-11-22 20:00:00 UTC', '2026-11-22', 80.0],
        [$otherStore, 0, '2026-11-22 20:00:00 UTC', '2026-11-22', 70.0],
    ];

    try {
        $write->insert($resource->getTableName('catalogrule/rule_product'), [
            'rule_id' => $ruleId,
            'from_time' => new DateTimeImmutable('2026-11-22 08:00:00 UTC')->getTimestamp(),
            'to_time' => new DateTimeImmutable('2026-11-23 07:59:59 UTC')->getTimestamp(),
            'customer_group_id' => 0,
            'product_id' => $productId,
            'website_id' => $firstWebsiteId,
            'action_operator' => 'by_percent',
            'action_amount' => 10,
            'action_stop' => 0,
            'sort_order' => 0,
        ]);
        foreach ($cases as [$view, $groupId, $timestamp, $day, $price]) {
            $write->insertOnDuplicate($priceTable, [
                'product_id' => $productId,
                'website_id' => (int) $view->getWebsiteId(),
                'customer_group_id' => $groupId,
                'rule_date' => $day,
                'rule_price' => $price,
            ], ['rule_price']);
        }

        $keys = [];
        $prices = [];
        foreach ($cases as [$view, $groupId, $timestamp, $day, $price]) {
            $now = new DateTimeImmutable($timestamp);
            $app->setCurrentStore($view);
            Mage::getSingleton('customer/session')->setCustomerGroupId($groupId);
            Mage::getResourceSingleton('catalog/product_indexer_price')->reindexProductIds([$productId]);
            $views = [];
            foreach ([null, $ruleId] as $selectedRule) {
                $widget = $app->getLayout()->createBlock('catalogrule/product_widget_onsale')
                    ->setTemplate('catalogrule/widget/onsale/content/onsale_grid.phtml')
                    ->setRuleId($selectedRule)
                    ->setOnlyInStock(false)
                    ->setProductsCount(100)
                    ->setCacheLifetime(null);
                $html = $widget->toHtml();
                $pattern = '~id="product-price-' . $productId . '-widget-onsale-grid"[^>]*>(.*?)</span>~s';
                $views[] = preg_match($pattern, $html, $matches)
                    ? (float) preg_replace('/[^0-9.]/', '', strip_tags($matches[1]))
                    : null;
                if ($selectedRule !== null) {
                    $keys[] = $widget->getCacheKey();
                }
            }
            $prices[] = $views;
        }

        expect($keys[1])->toBe($keys[2])
            ->and(array_unique($keys))->toHaveCount(5)
            ->and($prices)->toBe([[95.0, null], [90.0, 90.0], [90.0, 90.0], [95.0, null], [80.0, null], [70.0, null]]);
    } finally {
        $clockProperty->setValue($app, $locale);
        $app->setCurrentStore($previousStore);
        $rule->delete();
        $product->delete();
        deletePriceWebsite($siteCode);
    }
});
