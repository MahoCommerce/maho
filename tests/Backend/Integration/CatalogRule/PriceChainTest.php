<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The catalog rule price index chained rules with MySQL user variables (SET @price := ...), which
 * is a syntax error on PostgreSQL and SQLite, so applying rules failed outright there. These cover
 * the running calculation the rewrite has to keep: rules apply in order to the price the previous
 * one produced, and action_stop ends the chain.
 */

const CHAIN_RULE_NAME = 'pest-price-chain';

function chainWrite(): Maho\Db\Adapter\AdapterInterface
{
    return Mage::getSingleton('core/resource')->getConnection('core_write');
}

function chainTable(string $name): string
{
    return Mage::getSingleton('core/resource')->getTableName($name);
}

function chainProduct(): Mage_Catalog_Model_Product
{
    return Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToSelect('price')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('price', ['gt' => 0])
        ->setPageSize(1)
        ->getFirstItem();
}

/**
 * Write a rule chain straight into catalogrule_product. Going through the rule model instead would
 * rebuild that table from each rule's conditions and drop the chain we are trying to exercise.
 *
 * @param array<int, array<string, mixed>> $steps
 */
function seedRuleChain(int $productId, int $websiteId, array $steps): int
{
    $write = chainWrite();

    $write->insert(chainTable('catalogrule/rule'), [
        'name' => CHAIN_RULE_NAME,
        'is_active' => 1,
        'from_date' => '2026-01-01',
        'to_date' => '2026-12-31',
        'sort_order' => 0,
        'simple_action' => 'by_percent',
        'discount_amount' => 0,
        'stop_rules_processing' => 0,
    ]);
    $ruleId = (int) $write->lastInsertId(chainTable('catalogrule/rule'));

    foreach ($steps as $step) {
        $write->insert(chainTable('catalogrule/rule_product'), array_merge([
            'rule_id' => $ruleId,
            'from_time' => 0,
            'to_time' => 0,
            'customer_group_id' => 0,
            'product_id' => $productId,
            'website_id' => $websiteId,
        ], $step));
    }

    return $ruleId;
}

function runCatalogRuleIndexer(?int $time = null, ?int $productId = null): void
{
    $indexer = Mage::getModel($productId === null ? 'catalogrule/action_index_refresh' : 'catalogrule/action_index_refresh_row', [
        'connection' => chainWrite(),
        'factory'    => Mage::getModel('core/factory'),
        'resource'   => Mage::getResourceSingleton('catalogrule/rule'),
        'app'        => Mage::app(),
        'value'      => $productId,
    ]);
    if ($time === null) {
        $indexer->execute();
        return;
    }

    (new ReflectionMethod($indexer, '_reindex'))->invoke($indexer, Mage::app()->getWebsite(true), $time);
}

/**
 * @return array<int, array<string, mixed>>
 */
function indexedPrices(int $productId): array
{
    return chainWrite()->fetchAll(
        chainWrite()->select()
            ->from(chainTable('catalogrule/rule_product_price'))
            ->where('product_id = ?', $productId)
            ->order('rule_date'),
    );
}

afterEach(function () {
    $write = chainWrite();
    $ruleIds = $write->fetchCol(
        $write->select()->from(chainTable('catalogrule/rule'), 'rule_id')->where('name = ?', CHAIN_RULE_NAME),
    );
    if ($ruleIds) {
        $productIds = $write->fetchCol(
            $write->select()->from(chainTable('catalogrule/rule_product'), 'product_id')->where('rule_id IN (?)', $ruleIds),
        );
        $write->delete(chainTable('catalogrule/rule_product'), ['rule_id IN (?)' => $ruleIds]);
        $write->delete(chainTable('catalogrule/rule'), ['rule_id IN (?)' => $ruleIds]);
        if ($productIds) {
            $write->delete(chainTable('catalogrule/rule_product_price'), ['product_id IN (?)' => $productIds]);
        }
    }
});

it('applies each rule to the price the previous one produced', function () {
    $product = chainProduct();
    $productId = (int) $product->getId();
    $basePrice = (float) $product->getPrice();
    $websiteId = (int) Mage::app()->getWebsite(true)->getId();

    seedRuleChain($productId, $websiteId, [
        ['sort_order' => 0, 'action_operator' => 'by_percent', 'action_amount' => 10, 'action_stop' => 0],
        ['sort_order' => 1, 'action_operator' => 'by_fixed', 'action_amount' => 5, 'action_stop' => 0],
    ]);

    runCatalogRuleIndexer();

    $rows = indexedPrices($productId);
    // Yesterday, today and tomorrow are indexed so websites in any timezone read a valid price.
    expect($rows)->toHaveCount(3);

    foreach ($rows as $row) {
        expect((float) $row['rule_price'])->toEqualWithDelta($basePrice * 0.9 - 5, 0.0001)
            ->and($row['latest_start_date'])->toBe('2026-01-01')
            ->and($row['earliest_end_date'])->toBe('2026-12-31');
    }
});

it('stops the chain after a rule with action_stop', function () {
    $product = chainProduct();
    $productId = (int) $product->getId();
    $basePrice = (float) $product->getPrice();
    $websiteId = (int) Mage::app()->getWebsite(true)->getId();

    seedRuleChain($productId, $websiteId, [
        ['sort_order' => 0, 'action_operator' => 'by_percent', 'action_amount' => 10, 'action_stop' => 1],
        ['sort_order' => 1, 'action_operator' => 'to_fixed', 'action_amount' => 1, 'action_stop' => 0],
    ]);

    runCatalogRuleIndexer();

    $rows = indexedPrices($productId);

    expect($rows)->not->toBeEmpty();
    foreach ($rows as $row) {
        expect((float) $row['rule_price'])->toEqualWithDelta($basePrice * 0.9, 0.0001);
    }
});

it('indexes nothing for a rule whose window has passed', function () {
    $product = chainProduct();
    $productId = (int) $product->getId();
    $websiteId = (int) Mage::app()->getWebsite(true)->getId();

    $ruleId = seedRuleChain($productId, $websiteId, [
        ['sort_order' => 0, 'action_operator' => 'by_percent', 'action_amount' => 10, 'action_stop' => 0],
    ]);
    chainWrite()->update(
        chainTable('catalogrule/rule_product'),
        ['from_time' => strtotime('2020-01-01 UTC'), 'to_time' => strtotime('2020-12-31 UTC')],
        ['rule_id = ?' => $ruleId],
    );

    runCatalogRuleIndexer();

    expect(indexedPrices($productId))->toBeEmpty();
});

it('serves the price for the rules active on the store date', function (
    bool $stop,
    string $firstEnd,
    string $secondStart,
    array $expectedPrices,
): void {
    $app = Mage::app();
    $store = $app->getDefaultStoreView();
    $previousStore = $app->getStore();
    $previousTimezone = $store->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
    $storeId = (int) $store->getId();
    $websiteId = (int) $store->getWebsiteId();
    $time = new DateTimeImmutable('2026-11-22 12:00:00 UTC');
    $product = Mage::getModel('catalog/product')->setStoreId(0);
    $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId($product->getDefaultAttributeSetId())
        ->setSku('catalog-rule-expiration-' . uniqid())
        ->setName('Catalog promotion')
        ->setPrice(100.0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setWebsiteIds([$websiteId])
        ->save();
    $productId = (int) $product->getId();
    $locale = $app->getLocale();
    $localeProperty = new ReflectionProperty($app, '_locale');

    try {
        seedRuleChain($productId, $websiteId, [
            [
                'sort_order' => 0, 'action_operator' => 'by_percent', 'action_amount' => 10, 'action_stop' => (int) $stop,
                'from_time' => $time->modify('-2 days')->getTimestamp(),
                'to_time' => $time->modify($firstEnd)->getTimestamp(),
            ],
            [
                'sort_order' => 1, 'action_operator' => 'by_percent', 'action_amount' => 5, 'action_stop' => 0,
                'from_time' => $time->modify($secondStart)->getTimestamp(),
                'to_time' => $time->modify('+1 day')->getTimestamp(),
            ],
        ]);
        runCatalogRuleIndexer($time->getTimestamp(), $productId);
        $prices = array_map(static fn(array $row): float => (float) $row['rule_price'], indexedPrices($productId));

        $store->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, 'Pacific/Kiritimati');
        $app->setCurrentStore($store);
        $app->loadAreaPart(Mage_Core_Model_App_Area::AREA_FRONTEND, Mage_Core_Model_App_Area::PART_EVENTS);
        $fixedLocale = $this->createPartialMock(Mage_Core_Model_Locale::class, ['utcToStore']);
        $fixedLocale->method('utcToStore')->willReturnCallback(
            static fn(mixed $store = null, string|int|DateTimeInterface|null $date = null): DateTimeImmutable
                => $locale->utcToStore($store, $date ?? $time),
        );
        $localeProperty->setValue($app, $fixedLocale);
        $storeProduct = Mage::getModel('catalog/product')->setStoreId($storeId)->load($productId)->setCustomerGroupId(0);
        $finalPrice = (float) $storeProduct->getFinalPrice(1);

        expect($fixedLocale->utcToStore($store)->format('Y-m-d'))->toBe('2026-11-23')
            ->and($finalPrice)->toBe(95.0)
            ->and($prices)->toBe($expectedPrices);
    } finally {
        $localeProperty->setValue($app, $locale);
        $store->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $previousTimezone);
        $app->setCurrentStore($previousStore);
        $product->delete();
    }
})->with([
    'expired discount' => [false, 'today 23:59:59', '-1 day', [85.5, 85.5, 95.0]],
    'expired discount that stops the chain' => [true, 'today 23:59:59', '-1 day', [90.0, 90.0, 95.0]],
    'inclusive start and end at the index time' => [false, '+0 seconds', '+0 seconds', [90.0, 85.5, 95.0]],
]);
