<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * With prices scoped per website, saving a product converts its price into every website's
 * currency. When there is no rate the amount used to be written unconverted under the other
 * currency's label, which is a wrong price a merchant cannot see. There is nothing to write
 * instead, so nothing is written.
 *
 * The second website's base currency is an ISO 4217 "X" code no real currency uses, so no
 * install has a rate for it.
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
 * Runs in afterEach, not afterAll: afterAll comes after the last tearDown() has Mage::reset()
 * the app, so a delete there fatals into a catch and the website leaks into every later test
 * file. Here the app is live and setUp()'s isSecureArea registration still stands.
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
    // Here rather than in each test: a rate seeded before a try block leaks to the next test when
    // the setup between the two throws, and the failure then points at the wrong test.
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
 * The price index cannot drop the website row itself: the build inner joins it, so a website
 * without one leaves the index and its whole catalog stops being listed. The rate column can be
 * null though, and the prices derived from it are read as no price rather than as zero, which is
 * the answer #1269 settled on: no rate, no derived price, catalog still listed.
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
 * The attribute backend is held by the eav/config singleton, so it lives as long as the process,
 * and a rate import is a normal thing for a process to do before it saves products: the queue
 * worker and the CLI both do exactly that. Whatever this backend remembers about rates has to be
 * able to change when the table does.
 */
it('converts a group price against a rate imported since it last answered', function () {
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'group_price');
    $websiteId = (int) missingRateWebsite()->getId();
    $priceData = [['website_id' => 0, 'cust_group' => 0, 'price' => 100.0]];

    // Asked once with no rate, which is where a memo of "no rate" would be taken.
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
 * The two tests below are characterization tests: they pin a decision, not a behaviour anyone
 * would call right. A store row the save cannot reconvert is left as it stands, so it can
 * disagree with what the merchant just entered. The alternative, deleting the row, writes a
 * destructive save into a code path #1269 removes outright: website prices become derived at read
 * time, and a value already persisted in a website scope is treated as the merchant's. Delete
 * these with that code, not before.
 *
 * Only a fixed price is converted (Option.php:110), so each has to keep the type fixed at the save
 * that must reach the skip, or the percent branch carries the write through and proves nothing.
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
 * The base price is the one write path here that seeds rather than derives: it converts once at
 * product creation and never again, which is what #1269 replaces with derivation at read time.
 * Until that lands, a website whose currency has no rate has to be offered nothing rather than the
 * default-scope amount under its own currency's label.
 *
 * Two things have to be arranged to reach the block at all, and the control test below is what
 * proves they were. afterSave() returns early for a product that already carries orig data for the
 * attribute, so the object is built rather than loaded; and it only converts for an attribute on
 * website scope, which the price scope setting flips on the attribute row itself, so the scope is
 * set on the shared instance in memory and put back.
 */
function missingRatePriceAttribute(): Mage_Catalog_Model_Resource_Eav_Attribute
{
    /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'price');

    return $attribute;
}

/**
 * The update is recorded rather than performed: what is pinned is what the backend offers for a
 * website it has no rate for, and addAttributeUpdate() writes straight to the entity table.
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
    $attribute = missingRatePriceAttribute();
    $wasGlobal = $attribute->getIsGlobal();
    $product = missingRatePriceProduct([$storeId], 100.0);

    try {
        $attribute->setIsGlobal(Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE);
        $attribute->getBackend()->afterSave($product);

        expect($product->attributeUpdates)->toBe([]);
    } finally {
        $attribute->setIsGlobal($wasGlobal);
    }
});

it('seeds a website that has a rate with the converted base price', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();
    $attribute = missingRatePriceAttribute();
    $wasGlobal = $attribute->getIsGlobal();
    $product = missingRatePriceProduct([$storeId], 100.0);

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);

    try {
        $attribute->setIsGlobal(Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE);
        $attribute->getBackend()->afterSave($product);

        expect($product->attributeUpdates)->toBe([
            ['code' => 'price', 'value' => 200.0, 'store' => $storeId],
        ]);
    } finally {
        $attribute->setIsGlobal($wasGlobal);
    }
});

/*
 * Downloadable link prices are the seventh write path of this family, and the one #1269 does not
 * reach: a link price stays merchant data seeded at creation, so skipping the write is the final
 * answer here rather than a step towards derivation. The code under test is
 * Mage_Downloadable_Model_Resource_Link; it is exercised here because the website, its currency
 * and the price scope are the fixture above.
 */
function missingRateDownloadableLink(): Mage_Downloadable_Model_Link
{
    /** @var Mage_Downloadable_Model_Link $link */
    $link = Mage::getModel('downloadable/link');

    // Both defaults on, so creating the row writes neither a title nor a price and the seeding
    // call below is the only one the assertions can be reading.
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
 * A fresh object rather than the saved one: the seeding runs only for a link whose orig data does
 * not yet name it, which is how the resource tells a creation from an edit.
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
