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
function seedRuleChain(
    int $productId,
    int $websiteId,
    array $steps,
    string $fromDate = '2026-01-01',
    string $toDate = '2026-12-31',
): int {
    $write = chainWrite();

    $write->insert(chainTable('catalogrule/rule'), [
        'name' => CHAIN_RULE_NAME,
        'is_active' => 1,
        'from_date' => $fromDate,
        'to_date' => $toDate,
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

function createCatalogPromotionProduct(int $websiteId): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product')->setStoreId(0);
    $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId($product->getDefaultAttributeSetId())
        ->setSku('catalog-rule-promotion-' . uniqid())
        ->setName('Catalog promotion')
        ->setPrice(100.0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setWebsiteIds([$websiteId])
        ->save();
    return $product;
}

it('keeps a rule without a start date in the price for the previous store day', function (): void {
    $websiteId = (int) Mage::app()->getWebsite(true)->getId();
    $product = createCatalogPromotionProduct($websiteId);
    $productId = (int) $product->getId();
    try {
        $rule = Mage::getModel('catalogrule/rule')->setName(CHAIN_RULE_NAME)
            ->setIsActive(true)->setWebsiteIds([$websiteId])->setCustomerGroupIds([0])
            ->setSimpleAction('by_percent')->setDiscountAmount(5)->save();
        $rule->setProductsFilter([$productId]);
        $resource = Mage::getResourceSingleton('catalogrule/rule');
        $refreshAt = new DateTimeImmutable('today 01:00 UTC');
        $storeDay = $refreshAt->setTimezone(new DateTimeZone('America/Los_Angeles'))->format('Y-m-d');
        for ($run = 0; $run < 2; $run++) {
            $resource->updateRuleProductData($rule);
            runCatalogRuleIndexer($refreshAt->getTimestamp(), $productId);
            $prices = array_column(indexedPrices($productId), 'rule_price', 'rule_date');
            expect($prices)->toHaveKey($storeDay)
                ->and((float) $prices[$storeDay])->toBe(95.0);
        }
    } finally {
        $product->delete();
    }
});

it('matches scheduled rules for the indexed dates with the bounds of each website', function (bool $byProduct): void {
    $website = Mage::app()->getWebsite(true);
    $other = createPriceWebsite('rule_window');
    $timezonePath = Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE;
    Mage::getConfig()->setNode('websites/rule_window/' . $timezonePath, 'Pacific/Kiritimati');
    $timezones = [
        (int) $website->getId() => new DateTimeZone($website->getConfig($timezonePath)),
        (int) $other->getId() => new DateTimeZone('Pacific/Kiritimati'),
    ];
    $product = createPriceWebsiteProduct('rule-window', 100.0, $other);
    $productId = (int) $product->getId();
    $today = new DateTimeImmutable('today UTC');
    $resource = Mage::getResourceSingleton('catalogrule/rule');
    try {
        $ruleIds = [];
        $expected = [];
        foreach (range(-2, 3) as $offset) {
            $date = $today->modify("$offset days")->format('Y-m-d');
            $rule = Mage::getModel('catalogrule/rule')->setName(CHAIN_RULE_NAME)
                ->setIsActive(true)->setWebsiteIds(array_keys($timezones))->setCustomerGroupIds([0])
                ->setFromDate($date)->setToDate($date)
                ->setSimpleAction('by_percent')->setDiscountAmount(10)->save();
            $ruleIds[$offset] = (int) $rule->getId();
            if (!$byProduct) {
                $rule->setProductsFilter([$productId]);
                $resource->updateRuleProductData($rule);
            }
            if (abs($offset) <= 1) {
                foreach ($timezones as $websiteId => $timezone) {
                    $expected[$offset][] = [
                        (int) $rule->getId(), $websiteId,
                        new DateTimeImmutable($date, $timezone)->getTimestamp(),
                        new DateTimeImmutable($date . ' 23:59:59', $timezone)->getTimestamp(),
                    ];
                }
            }
        }
        if ($byProduct) {
            Mage::getModel('catalogrule/rule')->applyAllRulesToProduct($productId);
        }
        if ($today->format('Y-m-d') !== gmdate('Y-m-d')) {
            // These two rules can enter or leave the window while a refresh straddles UTC midnight.
            unset($ruleIds[-1], $ruleIds[2], $expected[-1]);
        }
        $rows = chainWrite()->fetchAll(chainWrite()->select()
            ->from(chainTable('catalogrule/rule_product'), ['rule_id', 'website_id', 'from_time', 'to_time'])
            ->where('product_id = ?', $productId)->where('rule_id IN (?)', array_values($ruleIds))->order(['rule_id', 'website_id']));
        expect(array_map(static fn(array $row): array => array_map(intval(...), array_values($row)), $rows))
            ->toBe(array_merge(...array_values($expected)));
    } finally {
        $product->delete();
        deletePriceWebsite('rule_window');
    }
})->with(['rule refresh' => false, 'product refresh' => true]);

it('replaces only the requested website until the product is reapplied completely', function (bool $preloaded): void {
    $otherWebsite = createPriceWebsite('rule_refresh_scope');
    $product = createPriceWebsiteProduct('rule-refresh-scope', 100.0, $otherWebsite);
    $productId = (int) $product->getId();
    $websiteId = (int) Mage::app()->getWebsite(true)->getId();
    $otherWebsiteId = (int) $otherWebsite->getId();
    try {
        $rule = Mage::getModel('catalogrule/rule')->setName(CHAIN_RULE_NAME)
            ->setIsActive(true)->setWebsiteIds([$websiteId, $otherWebsiteId])->setCustomerGroupIds([0])
            ->setSimpleAction('by_percent')->setDiscountAmount(5)->save();
        $rule->setProductsFilter([$productId]);
        $resource = Mage::getResourceSingleton('catalogrule/rule');
        $resource->updateRuleProductData($rule);
        $rule->setWebsiteIds([$websiteId])->setDiscountAmount(10)->save();

        if ($preloaded) {
            $rule->loadProductRules($product);
        }
        $rule->applyToProduct($preloaded ? $product : $productId, [$websiteId]);
        $date = gmdate('Y-m-d');
        expect((float) $resource->getRulePrice($date, $websiteId, 0, $productId))->toBe(90.0)
            ->and((float) $resource->getRulePrice($date, $otherWebsiteId, 0, $productId))->toBe(95.0);

        $rule->setDiscountAmount(20)->save();
        if ($preloaded) {
            $rule->setWebsiteIds([$websiteId, $otherWebsiteId])->save();
            $observer = Mage::getModel('catalogrule/observer');
            $event = new Maho\Event\Observer(['event' => new Maho\Event(['product' => $product])]);
            $observer->loadProductRules($event);
            $failingResource = new class extends Mage_CatalogRule_Model_Resource_Rule {
                #[\Override]
                public function validateProduct(Mage_CatalogRule_Model_Rule $rule, Maho\DataObject $product, $websiteIds = [])
                {
                    throw new RuntimeException('Rule validation failed');
                }
            };
            expect(fn() => $failingResource->applyToProductOnAllWebsites($rule, $product, [$websiteId]))
                ->toThrow(RuntimeException::class, 'Rule validation failed')
                ->and($resource->getRuleProductIds($rule->getId()))->toHaveCount(2);

            $product->setWebsiteIds([$websiteId])->save();
            $observer->applyAllRulesOnProduct($event);
        } else {
            $rule->applyAllRulesToProduct($productId);
        }
        expect((float) $resource->getRulePrice($date, $websiteId, 0, $productId))->toBe(80.0)
            ->and($resource->getRulePrice($date, $otherWebsiteId, 0, $productId))->toBeFalse();
    } finally {
        $product->delete();
        deletePriceWebsite('rule_refresh_scope');
    }
})->with([
    'product ID' => [false],
    'preloaded product and admin observers' => [true],
]);

it('rebuilds a filtered rule from comma-separated website IDs and drops a removed product website', function (
    bool $scalarFilter,
    bool $grouped = false,
): void {
    $otherWebsite = createPriceWebsite('rule_bulk_scope');
    $product = createPriceWebsiteProduct('rule-bulk-scope', 100.0, $otherWebsite);
    $productId = (int) $product->getId();
    $websiteId = (int) Mage::app()->getWebsite(true)->getId();
    $otherWebsiteId = (int) $otherWebsite->getId();
    try {
        $rule = Mage::getModel('catalogrule/rule')->setName(CHAIN_RULE_NAME)
            ->setIsActive(true)->setWebsiteIds([$websiteId, $otherWebsiteId])->setCustomerGroupIds([0])
            ->setSimpleAction('by_percent')->setDiscountAmount(5)->save();
        $rule->setData('website_ids', $websiteId . ',' . $otherWebsiteId);
        $rule->setProductsFilter($scalarFilter ? $productId : [$productId]);
        if ($grouped) {
            $rule->setConditions(new class extends Mage_CatalogRule_Model_Rule_Condition_Combine {
                #[\Override]
                public function collectValidatedAttributes(Mage_Catalog_Model_Resource_Product_Collection $productCollection): self
                {
                    parent::collectValidatedAttributes($productCollection);
                    $productCollection->getSelect()->group('e.entity_id');
                    return $this;
                }
            });
        }
        $resource = Mage::getResourceSingleton('catalogrule/rule');
        $sites = static fn(): array => array_map(intval(...), chainWrite()->fetchCol(
            chainWrite()->select()
                ->from(chainTable('catalogrule/rule_product'), 'website_id')
                ->where('rule_id = ?', $rule->getId())
                ->where('product_id = ?', $productId)
                ->order('website_id'),
        ));

        $resource->updateRuleProductData($rule);
        expect($sites())->toBe([$websiteId, $otherWebsiteId]);

        $product->setWebsiteIds([$websiteId])->save();
        $resource->updateRuleProductData($rule);
        expect($sites())->toBe([$websiteId]);
    } finally {
        if (chainWrite()->getTransactionLevel() > 0) {
            chainWrite()->rollBack();
        }
        $product->delete();
        deletePriceWebsite('rule_bulk_scope');
    }
})->with([
    'list of product IDs' => [false],
    'single product ID' => [true],
    'grouped collection' => [false, true],
]);

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
    $product = createCatalogPromotionProduct($websiteId);
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

it('uses the store calendar for native date bounds and timestamps for custom bounds', function (
    string $promotionDay,
    string $nextDay,
    string $startUtc,
    string $endUtc,
    array $overrides,
    array $expectedPrices,
): void {
    $app = Mage::app();
    $store = $app->getDefaultStoreView();
    $previousStore = $app->getStore();
    $timezonePath = Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE;
    $previousTimezone = $store->getConfig($timezonePath);
    $websitePath = 'websites/' . $app->getWebsite($store->getWebsiteId())->getCode() . '/' . $timezonePath;
    $previousWebsiteZone = (string) Mage::getConfig()->getNode($websitePath);
    $storeId = (int) $store->getId();
    $websiteId = (int) $store->getWebsiteId();
    $product = createCatalogPromotionProduct($websiteId);
    $productId = (int) $product->getId();
    $locale = $app->getLocale();
    $localeProperty = new ReflectionProperty($app, '_locale');

    try {
        $store->setConfig($timezonePath, 'America/Los_Angeles');
        Mage::getConfig()->setNode($websitePath, 'America/Los_Angeles');
        $app->setCurrentStore($store);
        $app->loadAreaPart(Mage_Core_Model_App_Area::AREA_FRONTEND, Mage_Core_Model_App_Area::PART_EVENTS);

        $start = $locale->storeToUtc($store, $promotionDay);
        $end = $locale->storeToUtc($store, $promotionDay . ' 23:59:59');
        expect($start->format('Y-m-d H:i:s'))->toBe($startUtc)
            ->and($end->format('Y-m-d H:i:s'))->toBe($endUtc);

        $promotion = [
            'sort_order' => 0, 'action_operator' => 'by_percent', 'action_amount' => 10, 'action_stop' => 0,
            'from_time' => $start->getTimestamp(),
            'to_time' => $end->getTimestamp(),
        ];
        foreach ($overrides as $bound => $date) {
            $promotion[$bound] = (new DateTimeImmutable($date))->getTimestamp();
        }
        seedRuleChain($productId, $websiteId, [$promotion], $promotionDay, $promotionDay);
        seedRuleChain($productId, $websiteId, [
            ['sort_order' => 1, 'action_operator' => 'by_percent', 'action_amount' => 5, 'action_stop' => 0],
        ]);
        runCatalogRuleIndexer((new DateTimeImmutable($nextDay . ' 01:00:00 UTC'))->getTimestamp(), $productId);

        foreach ([$promotionDay, $nextDay] as $i => $day) {
            $readAt = $locale->storeToUtc($store, $day . ($i === 0 ? ' 23:59:59' : ' 00:00:01'));
            $fixedLocale = $this->createPartialMock(Mage_Core_Model_Locale::class, ['utcToStore']);
            $fixedLocale->method('utcToStore')->willReturnCallback(
                static fn(mixed $store = null, string|int|DateTimeInterface|null $date = null): DateTimeImmutable
                    => $locale->utcToStore($store, $date ?? $readAt),
            );
            $localeProperty->setValue($app, $fixedLocale);
            $storeProduct = Mage::getModel('catalog/product')->setStoreId($storeId)->load($productId)->setCustomerGroupId(0);
            expect((float) $storeProduct->getFinalPrice(1))->toBe($expectedPrices[$i]);
        }
    } finally {
        $localeProperty->setValue($app, $locale);
        $store->setConfig($timezonePath, $previousTimezone);
        Mage::getConfig()->setNode($websitePath, $previousWebsiteZone);
        $app->setCurrentStore($previousStore);
        $product->delete();
    }
})->with([
    'Los Angeles midnight' => [
        '2026-11-22', '2026-11-23', '2026-11-22 08:00:00', '2026-11-23 07:59:59', [], [85.5, 95.0],
    ],
    'Los Angeles spring DST transition' => [
        '2026-03-08', '2026-03-09', '2026-03-08 08:00:00', '2026-03-09 06:59:59', [], [85.5, 95.0],
    ],
    'Los Angeles autumn DST transition' => [
        '2026-11-01', '2026-11-02', '2026-11-01 07:00:00', '2026-11-02 07:59:59', [], [85.5, 95.0],
    ],
    'custom start keeps native end' => [
        '2026-11-22', '2026-11-23', '2026-11-22 08:00:00', '2026-11-23 07:59:59',
        ['from_time' => '2026-11-22 00:00:00 UTC'], [85.5, 95.0],
    ],
    'custom end keeps native start' => [
        '2026-11-22', '2026-11-23', '2026-11-22 08:00:00', '2026-11-23 07:59:59',
        ['to_time' => '2026-11-23 09:59:59 UTC'], [85.5, 85.5],
    ],
    'custom start follows the index timestamp' => [
        '2026-11-22', '2026-11-23', '2026-11-22 08:00:00', '2026-11-23 07:59:59',
        ['from_time' => '2026-11-22 03:00:00 UTC'], [95.0, 95.0],
    ],
]);

it('indexes native and older rule windows on the civil date for each website', function (): void {
    $app = Mage::app();
    $previousStore = $app->getStore()->getCode();
    $eastWebsite = createPriceWebsite('catalog_rule_boundary_east');
    $admin = $app->getStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    $timezonePath = Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE;
    $previousAdminZone = $admin->getConfig($timezonePath);
    $currentWebsite = $app->getWebsite($app->getDefaultStoreView()->getWebsiteId());
    $eastStore = $app->getStore($eastWebsite->getDefaultStore()->getId());
    $previousEastStoreZone = $eastStore->getConfig($timezonePath);
    $westPath = 'websites/' . $currentWebsite->getCode() . '/' . $timezonePath;
    $eastPath = 'websites/' . $eastWebsite->getCode() . '/' . $timezonePath;
    $previousWestZone = (string) Mage::getConfig()->getNode($westPath);
    $previousEastZone = (string) Mage::getConfig()->getNode($eastPath);
    $product = createPriceWebsiteProduct('catalog-site-civil-date', 100.0, $eastWebsite);
    try {
        $admin->setConfig($timezonePath, 'America/New_York');
        $app->setCurrentStore($admin);
        Mage::getConfig()->setNode($westPath, 'America/Los_Angeles');
        Mage::getConfig()->setNode($eastPath, 'Pacific/Kiritimati');
        $resource = Mage::getResourceSingleton('catalogrule/rule');
        $indexer = Mage::getModel('catalogrule/action_index_refresh_row', [
            'connection' => chainWrite(), 'factory' => Mage::getModel('core/factory'),
            'resource' => $resource, 'app' => $app, 'value' => $product->getId(),
        ]);
        $reindex = new ReflectionMethod($indexer, '_reindex');
        foreach ([$currentWebsite, $eastWebsite] as $website) {
            $websiteId = (int) $website->getId();
            $timezone = new DateTimeZone($website->getConfig($timezonePath));
            foreach ([$timezone, new DateTimeZone('America/New_York')] as $writerZone) {
                $ruleId = seedRuleChain((int) $product->getId(), $websiteId, [[
                    'sort_order' => 0, 'action_operator' => 'by_percent', 'action_amount' => 10, 'action_stop' => 0,
                    'from_time' => new DateTimeImmutable('2026-06-01', $writerZone)->getTimestamp(),
                    'to_time' => new DateTimeImmutable('2026-06-01 23:59:59', $writerZone)->getTimestamp(),
                ]], '2026-06-01', '2026-06-01');
                $hour = $website === $eastWebsite ? '20' : '01';
                $reindex->invoke($indexer, $website, strtotime("2026-06-01 $hour:00:00 UTC"));
                expect((float) $resource->getRulePrice('2026-06-01', $websiteId, 0, (int) $product->getId()))->toBe(90.0)
                    ->and($resource->getRulePrice('2026-05-31', $websiteId, 0, (int) $product->getId()))->toBeFalse()
                    ->and($resource->getRulePrice('2026-06-02', $websiteId, 0, (int) $product->getId()))->toBeFalse();
                chainWrite()->delete(chainTable('catalogrule/rule_product'), ['rule_id = ?' => $ruleId]);
            }
        }

        $admin->setConfig($timezonePath, 'UTC');
        $westWebsiteId = (int) $currentWebsite->getId();
        $productId = (int) $product->getId();
        seedRuleChain($productId, $westWebsiteId, [[
            'sort_order' => 0, 'action_operator' => 'by_percent', 'action_amount' => 10, 'action_stop' => 0,
            'from_time' => strtotime('2026-06-01 04:00:00 UTC'),
        ]], '2026-06-01');
        $eastStore->setConfig($timezonePath, 'America/New_York');
        foreach ([$admin, $eastStore] as $store) {
            $app->setCurrentStore($store);
            $reindex->invoke($indexer, $currentWebsite, strtotime('2026-06-01 01:00:00 UTC'));
            expect($resource->getRulePrice('2026-06-01', $westWebsiteId, 0, $productId))->toBeFalse()
                ->and((float) $resource->getRulePrice('2026-06-02', $westWebsiteId, 0, $productId))->toBe(90.0);
        }
    } finally {
        $eastStore->setConfig($timezonePath, $previousEastStoreZone);
        $admin->setConfig($timezonePath, $previousAdminZone);
        Mage::getConfig()->setNode($westPath, $previousWestZone);
        Mage::getConfig()->setNode($eastPath, $previousEastZone);
        $app->setCurrentStore($previousStore);
        $product->delete();
        deletePriceWebsite('catalog_rule_boundary_east');
    }
});
