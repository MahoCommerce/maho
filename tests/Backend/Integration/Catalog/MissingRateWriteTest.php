<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * With prices scoped per website, a save converts the price into every website's currency; with
 * no rate, nothing is written rather than an unconverted amount under the other currency's label.
 * The second website's base currency is an ISO 4217 "X" code, so no install has a rate for it.
 */
const MISSING_RATE_CODE = 'missing_rate_ws';
const MISSING_RATE_CURRENCY = 'XTN';

function missingRateWebsite(): Mage_Core_Model_Website
{
    /** @var Mage_Core_Model_Website $website */
    $website = Mage::getModel('core/website')->load(MISSING_RATE_CODE, 'code');
    if ($website->getId()) {
        return $website;
    }

    $website = Mage::getModel('core/website')
        ->setCode(MISSING_RATE_CODE)
        ->setName('Missing Rate Website')
        ->setSortOrder(98)
        ->save();

    $group = Mage::getModel('core/store_group')
        ->setWebsiteId((int) $website->getId())
        ->setName('Missing Rate Group')
        ->setRootCategoryId((int) Mage::app()->getStore(1)->getRootCategoryId())
        ->save();

    $store = Mage::getModel('core/store')
        ->setCode(MISSING_RATE_CODE)
        ->setWebsiteId((int) $website->getId())
        ->setGroupId((int) $group->getId())
        ->setName('Missing Rate Store')
        ->setIsActive(1)
        ->setSortOrder(98)
        ->save();

    $website->setDefaultGroupId((int) $group->getId())->save();
    $group->setDefaultStoreId((int) $store->getId())->save();
    Mage::app()->reinitStores();

    return Mage::getModel('core/website')->load(MISSING_RATE_CODE, 'code');
}

/**
 * Price scope and the second website's currency, in memory only: a test that writes core_config_data
 * would leave the whole install on website price scope if it died mid-run.
 */
function missingRateConfigure(): void
{
    Mage::getConfig()->setNode('websites/' . MISSING_RATE_CODE . '/catalog/price/scope', 1);
    Mage::getConfig()->setNode('websites/' . MISSING_RATE_CODE . '/currency/options/base', MISSING_RATE_CURRENCY);

    foreach (Mage::app()->getStores(true) as $store) {
        $store->setConfig('catalog/price/scope', 1);
    }
    Mage::app()->getStore(MISSING_RATE_CODE)->setConfig('currency/options/base', MISSING_RATE_CURRENCY);
}

function missingRateRestore(): void
{
    Mage::getConfig()->setNode('websites/' . MISSING_RATE_CODE . '/catalog/price/scope', 0);
    foreach (Mage::app()->getStores(true) as $store) {
        $store->setConfig('catalog/price/scope', 0);
    }
}

/**
 * In afterEach, not afterAll: afterAll runs after Mage::reset(), so a delete there fatals and
 * the website leaks into every later test file.
 */
function missingRateDeleteWebsite(): void
{
    $website = Mage::getModel('core/website')->load(MISSING_RATE_CODE, 'code');
    if (!$website->getId()) {
        return;
    }

    foreach ($website->getStores() as $store) {
        $store->delete();
    }
    foreach ($website->getGroups() as $group) {
        $group->delete();
    }
    $website->delete();
    Mage::app()->reinitStores();
}

function missingRateDropRate(): void
{
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->delete(
        $resource->getTableName('directory/currency_rate'),
        ['currency_to = ?' => MISSING_RATE_CURRENCY],
    );
    Mage_Directory_Model_Resource_Currency::clearRateCache();
}

/** @return array{price: float, price_type: string}|null */
function missingRateOptionPriceRow(int $optionId, int $storeId): ?array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $row = $adapter->fetchRow(
        $adapter->select()
            ->from($resource->getTableName('catalog/product_option_price'), ['price', 'price_type'])
            ->where('option_id = ?', $optionId)
            ->where('store_id = ?', $storeId),
    );

    return $row ? ['price' => (float) $row['price'], 'price_type' => (string) $row['price_type']] : null;
}

beforeEach(function () {
    missingRateWebsite();
    missingRateConfigure();
});

afterEach(function () {
    missingRateRestore();
    // Here rather than per test: a seeded rate leaks to the next test when setup throws
    missingRateDropRate();
    missingRateDeleteWebsite();
});

it('leaves a website with no rate out of the group price rates', function () {
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'group_price');
    $websiteId = (int) missingRateWebsite()->getId();

    $prepared = $attribute->getBackend()->preparePriceData(
        [['website_id' => 0, 'cust_group' => 0, 'price' => 100.0]],
        Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
        $websiteId,
    );

    expect($prepared)->toBe([]);
});

/*
 * The build inner joins this table, so the row must stay or the website's whole catalog leaves
 * the index; a null rate drops only the derived prices (#1269).
 */
it('keeps a website with no rate in the price index, at no rate rather than at parity', function () {
    $websiteId = (int) missingRateWebsite()->getId();
    $baseWebsiteId = (int) Mage::app()->getStore(1)->getWebsiteId();

    $resource = Mage::getResourceSingleton('catalog/product_indexer_price');
    (new ReflectionMethod($resource, '_prepareWebsiteDateTable'))->invoke($resource);

    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $table = Mage::getSingleton('core/resource')->getTableName('catalog/product_index_website');
    $rates = $adapter->fetchPairs($adapter->select()->from($table, ['website_id', 'rate']));

    expect($rates)->toHaveKey($websiteId);
    expect($rates[$websiteId])->toBeNull();
    expect((float) $rates[$baseWebsiteId])->toBe(1.0);
});

it('converts the group price for a website that has a rate', function () {
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'group_price');
    $websiteId = (int) missingRateWebsite()->getId();

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);

    $prepared = $attribute->getBackend()->preparePriceData(
        [['website_id' => 0, 'cust_group' => 0, 'price' => 100.0]],
        Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
        $websiteId,
    );

    expect($prepared['0']['price'])->toBe(200.0);
});

/*
 * The backend is held by the eav/config singleton for the life of the process, so its rate
 * answers must change when the table does.
 */
it('converts a group price against a rate imported since it last answered', function () {
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'group_price');
    $websiteId = (int) missingRateWebsite()->getId();
    $priceData = [['website_id' => 0, 'cust_group' => 0, 'price' => 100.0]];

    // Asked once with no rate, where a memo of "no rate" would be taken
    expect($attribute->getBackend()->preparePriceData(
        $priceData,
        Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
        $websiteId,
    ))->toBe([]);

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);

    $prepared = $attribute->getBackend()->preparePriceData(
        $priceData,
        Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
        $websiteId,
    );

    expect($prepared['0']['price'])->toBe(200.0);
});

/*
 * Characterization tests, delete with #1269: a store row the save cannot reconvert is left as it
 * stands. Only a fixed price is converted, so the save that must reach the skip stays type fixed.
 */
function missingRateOption(float $price, string $priceType): Mage_Catalog_Model_Product_Option
{
    /** @var Mage_Catalog_Model_Product_Option $option */
    $option = Mage::getModel('catalog/product_option');
    $option->setProductId((int) loadSimplePricedProduct()->getId())
        ->setStoreId(0)
        ->setType(Mage_Catalog_Model_Product_Option::OPTION_TYPE_FIELD)
        ->setIsRequire(0)
        ->setSortOrder(0)
        ->setTitle('Missing rate option')
        ->setPrice($price)
        ->setPriceType($priceType)
        ->save();

    return $option;
}

it('leaves a store row on the price type it can no longer convert away from', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();
    $option = missingRateOption(15.0, 'percent');

    try {
        // A percent price is not converted, so this row lands without any rate at all.
        $option->setStoreId($storeId)->setPrice(15.0)->setPriceType('percent')->save();
        expect(missingRateOptionPriceRow((int) $option->getId(), $storeId))
            ->toBe(['price' => 15.0, 'price_type' => 'percent']);

        // The merchant makes it a fixed 300, which needs a rate this store has not got.
        $option->setStoreId($storeId)->setPrice(300.0)->setPriceType('fixed')->save();

        expect(missingRateOptionPriceRow((int) $option->getId(), $storeId))
            ->toBe(['price' => 15.0, 'price_type' => 'percent']);
    } finally {
        $option->setStoreId(0)->delete();
    }
});

it('leaves a converted store row at the amount it was converted at', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);
    $option = missingRateOption(100.0, 'fixed');

    try {
        $option->setStoreId($storeId)->setPrice(100.0)->setPriceType('fixed')->save();
        expect(missingRateOptionPriceRow((int) $option->getId(), $storeId))
            ->toBe(['price' => 200.0, 'price_type' => 'fixed']);

        missingRateDropRate();

        $option->setStoreId($storeId)->setPrice(300.0)->setPriceType('fixed')->save();

        expect(missingRateOptionPriceRow((int) $option->getId(), $storeId))
            ->toBe(['price' => 200.0, 'price_type' => 'fixed']);
    } finally {
        $option->setStoreId(0)->delete();
    }
});

/*
 * The base price seeds once at product creation (#1269 replaces this with derivation at read
 * time). The converting branch needs a built, not loaded, product and website price scope.
 */

/**
 * The update is recorded, not performed: addAttributeUpdate() writes straight to the entity table.
 */
function missingRatePriceProduct(array $storeIds, float $price): Mage_Catalog_Model_Product
{
    $product = new class extends Mage_Catalog_Model_Product {
        /** @var list<array{code: string, value: mixed, store: int}> */
        public array $attributeUpdates = [];

        #[\Override]
        public function addAttributeUpdate($code, $value, $store)
        {
            $this->attributeUpdates[] = ['code' => (string) $code, 'value' => $value, 'store' => (int) $store];
        }
    };

    return $product->setStoreId(0)->setStoreIds($storeIds)->setPrice($price);
}

it('offers a website with no rate no base price of its own', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();
    $product = missingRatePriceProduct([$storeId], 100.0);

    Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'price')
        ->getBackend()->afterSave($product);

    expect($product->attributeUpdates)->toBe([]);
});

it('seeds a website that has a rate with the converted base price', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();
    $product = missingRatePriceProduct([$storeId], 100.0);

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);

    Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'price')
        ->getBackend()->afterSave($product);

    expect($product->attributeUpdates)->toBe([
        ['code' => 'price', 'value' => 200.0, 'store' => $storeId],
    ]);
});

/*
 * Downloadable link prices stay seeded at creation (#1269 does not reach them), so skipping the
 * write is the final answer. Under test: Mage_Downloadable_Model_Resource_Link.
 */
function missingRateDownloadableLink(): Mage_Downloadable_Model_Link
{
    /** @var Mage_Downloadable_Model_Link $link */
    $link = Mage::getModel('downloadable/link');

    // Both defaults on, so creating the row writes neither a title nor a price
    return $link->setProductId((int) loadSimplePricedProduct()->getId())
        ->setSortOrder(0)
        ->setNumberOfDownloads(0)
        ->setIsShareable(2)
        ->setLinkType('url')
        ->setLinkUrl('https://example.com/missing-rate')
        ->setUseDefaultTitle(true)
        ->setUseDefaultPrice(true)
        ->save();
}

/**
 * A fresh object rather than the saved one: seeding runs only when orig data has no price yet.
 */
function missingRateLinkPriceSave(int $linkId, float $price, int $websiteId): void
{
    /** @var Mage_Downloadable_Model_Link $carrier */
    $carrier = Mage::getModel('downloadable/link');
    $carrier->setId($linkId)
        ->setLinkId($linkId)
        ->setStoreId(0)
        ->setWebsiteId(0)
        ->setPrice($price)
        ->setUseDefaultTitle(true)
        ->setUseDefaultPrice(false)
        ->setProductWebsiteIds([$websiteId]);

    Mage::getResourceSingleton('downloadable/link')->saveItemTitleAndPrice($carrier);
}

/** @return array<int, float> website id to the price seeded for it */
function missingRateLinkPrices(int $linkId): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $rows = $adapter->fetchPairs(
        $adapter->select()
            ->from($resource->getTableName('downloadable/link_price'), ['website_id', 'price'])
            ->where('link_id = ?', $linkId)
            ->order('website_id ASC'),
    );

    return array_map(static fn($price): float => (float) $price, $rows);
}

it('leaves a downloadable link price off a website with no rate', function () {
    $websiteId = (int) missingRateWebsite()->getId();
    $link = missingRateDownloadableLink();

    try {
        missingRateLinkPriceSave((int) $link->getId(), 100.0, $websiteId);

        expect(missingRateLinkPrices((int) $link->getId()))->toBe([0 => 100.0]);
    } finally {
        $link->delete();
    }
});

it('seeds a downloadable link price for a website that has a rate', function () {
    $websiteId = (int) missingRateWebsite()->getId();

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);
    $link = missingRateDownloadableLink();

    try {
        missingRateLinkPriceSave((int) $link->getId(), 100.0, $websiteId);

        expect(missingRateLinkPrices((int) $link->getId()))->toBe([0 => 100.0, $websiteId => 200.0]);
    } finally {
        $link->delete();
    }
});
