<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * With prices scoped per website, a save stores only the scope the merchant typed into, and a
 * website with no row of its own derives its price from the rate on read; with no rate, there is
 * no price rather than an unconverted amount under the other currency's label. The second
 * website's base currency is an ISO 4217 "X" code, so no install has a rate for it.
 */
const MISSING_RATE_CODE = 'missing_rate_ws';
const MISSING_RATE_CURRENCY = 'XTN';

function missingRateWebsite(): Mage_Core_Model_Website
{
    return createPriceWebsite(MISSING_RATE_CODE, 98);
}

function missingRateConfigure(): void
{
    configurePriceWebsite(MISSING_RATE_CODE, MISSING_RATE_CURRENCY);
}

function missingRateRestore(): void
{
    restorePriceScope(MISSING_RATE_CODE);
}

function missingRateDeleteWebsite(): void
{
    deletePriceWebsite(MISSING_RATE_CODE);
}

function missingRateDropRate(): void
{
    dropCurrencyRates(MISSING_RATE_CURRENCY);
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
    $this->currentStore = Mage::app()->getStore()->getId();
    missingRateWebsite();
    missingRateConfigure();
});

afterEach(function () {
    Mage::app()->setCurrentStore($this->currentStore);
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
 * Every price indexer inner joins this table, so a website with no row is left out of the index
 * by all of them at once. A website with no rate sells nothing (#1269), and this is the one place
 * that says so, rather than a clause each indexer has to remember.
 */
it('leaves a website with no rate out of the website table every price indexer joins', function () {
    $websiteId = (int) missingRateWebsite()->getId();
    $baseWebsiteId = (int) Mage::app()->getStore(1)->getWebsiteId();

    $resource = Mage::getResourceSingleton('catalog/product_indexer_price');
    (new ReflectionMethod($resource, '_prepareWebsiteDateTable'))->invoke($resource);

    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $table = Mage::getSingleton('core/resource')->getTableName('catalog/product_index_website');
    $rates = $adapter->fetchPairs($adapter->select()->from($table, ['website_id', 'rate']));

    expect($rates)->not->toHaveKey($websiteId);
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
 * A price the merchant types under a website is that website's own amount, so it is stored as
 * typed rather than multiplied by a rate on the way in. Conversion happens when the price is
 * read, from the default-scope row, for a store that has no row of its own.
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

it('stores a store row exactly as the merchant typed it', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();
    $option = missingRateOption(15.0, 'percent');

    try {
        // No rate is needed to store it, so the store with none takes the value too
        $option->setStoreId($storeId)->setPrice(300.0)->setPriceType('fixed')->save();

        expect(missingRateOptionPriceRow((int) $option->getId(), $storeId))
            ->toBe(['price' => 300.0, 'price_type' => 'fixed']);
    } finally {
        $option->setStoreId(0)->delete();
    }
});

it('stores a store row as typed even when a rate exists', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);
    $option = missingRateOption(100.0, 'fixed');

    try {
        $option->setStoreId($storeId)->setPrice(100.0)->setPriceType('fixed')->save();

        expect(missingRateOptionPriceRow((int) $option->getId(), $storeId))
            ->toBe(['price' => 100.0, 'price_type' => 'fixed']);
    } finally {
        $option->setStoreId(0)->delete();
    }
});

/** Option values keep their prices in a table of their own, with the same store scoping. */
function missingRateOptionValue(Mage_Catalog_Model_Product_Option $option, float $price, string $priceType): Mage_Catalog_Model_Product_Option_Value
{
    /** @var Mage_Catalog_Model_Product_Option_Value $value */
    $value = Mage::getModel('catalog/product_option_value');
    $value->setOptionId((int) $option->getId())
        ->setStoreId(0)
        ->setTitle('Missing rate value')
        ->setPrice($price)
        ->setPriceType($priceType)
        ->setSortOrder(0)
        ->save();

    return $value;
}

/** @return array{price: float, price_type: string}|null */
function missingRateOptionValuePriceRow(int $valueId, int $storeId): ?array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $row = $adapter->fetchRow(
        $adapter->select()
            ->from($resource->getTableName('catalog/product_option_type_price'), ['price', 'price_type'])
            ->where('option_type_id = ?', $valueId)
            ->where('store_id = ?', $storeId),
    );

    return $row ? ['price' => (float) $row['price'], 'price_type' => (string) $row['price_type']] : null;
}

it('stores an option value store row exactly as the merchant typed it', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);
    $option = missingRateOption(100.0, 'fixed');

    try {
        $value = missingRateOptionValue($option, 100.0, 'fixed');

        Mage::getModel('catalog/product_option_value')
            ->setId((int) $value->getId())
            ->setOptionId((int) $option->getId())
            ->setStoreId($storeId)
            ->setTitle('Missing rate value')
            ->setPrice(300.0)
            ->setPriceType('fixed')
            ->setSortOrder(0)
            ->save();

        expect(missingRateOptionValuePriceRow((int) $value->getId(), $storeId))
            ->toBe(['price' => 300.0, 'price_type' => 'fixed']);
    } finally {
        $option->setStoreId(0)->delete();
    }
});

/**
 * The storefront runs with the website's own store current, and an option reads its store from
 * there rather than from the collection.
 */
function missingRateOptionFromCollection(int $optionId, int $storeId): Mage_Catalog_Model_Product_Option
{
    Mage::app()->setCurrentStore($storeId);

    /** @var Mage_Catalog_Model_Product_Option $option */
    $option = Mage::getResourceModel('catalog/product_option_collection')
        ->addFieldToFilter('main_table.option_id', $optionId)
        ->addPriceToResult($storeId)
        ->getFirstItem();

    return $option;
}

/*
 * The option table has no store_id column, so a collection-loaded option carries none: the store
 * it was loaded for has to travel with it, or the rate gets resolved from whatever store happens
 * to be current, which in a CLI, cron or admin process is not the product's.
 */
it('derives an option price for the store it was loaded for, not the current one', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);
    $option = missingRateOption(100.0, 'fixed');

    try {
        // Current store deliberately left where it is, the way a CLI process would have it
        /** @var Mage_Catalog_Model_Product_Option $loaded */
        $loaded = Mage::getResourceModel('catalog/product_option_collection')
            ->addFieldToFilter('main_table.option_id', $option->getId())
            ->addPriceToResult($storeId)
            ->getFirstItem();

        expect($loaded->getPrice())->toBe(200.0);
    } finally {
        $option->setStoreId(0)->delete();
    }
});

it('prices the options of a collection-loaded product for the store the product was loaded for', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);
    $option = missingRateOption(100.0, 'fixed');

    try {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId($storeId)
            ->addIdFilter([$option->getProductId()])
            ->getFirstItem();

        expect($product->getProductOptionsCollection()->getItemById($option->getId())->getPrice())->toBe(200.0);
    } finally {
        $option->setStoreId(0)->delete();
    }
});

/*
 * The derived price must never be written back: an option loaded for a store and saved again has
 * to store the amount in its data, not the amount it would charge.
 */
it('does not write a derived option price back when a loaded option is saved for its store', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);
    $option = missingRateOption(100.0, 'fixed');

    try {
        $loaded = missingRateOptionFromCollection((int) $option->getId(), $storeId);
        expect($loaded->getPrice())->toBe(200.0);

        $loaded->setStoreId($storeId)->setProductId($option->getProductId())->save();

        expect(missingRateOptionPriceRow((int) $option->getId(), 0))
            ->toBe(['price' => 100.0, 'price_type' => 'fixed'])
            ->and(missingRateOptionPriceRow((int) $option->getId(), $storeId)['price'] ?? 100.0)->toBe(100.0);
    } finally {
        $option->setStoreId(0)->delete();
    }
});

it('does not write a derived option value price back either', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);
    $option = missingRateOption(0.0, 'fixed');
    $value = missingRateOptionValue($option, 50.0, 'fixed');

    try {
        Mage::app()->setCurrentStore($storeId);
        /** @var Mage_Catalog_Model_Product_Option_Value $loaded */
        $loaded = Mage::getResourceModel('catalog/product_option_value_collection')
            ->addFieldToFilter('main_table.option_type_id', $value->getId())
            ->addPriceToResult($storeId)
            ->getFirstItem();
        expect($loaded->getPrice())->toBe(100.0);

        $loaded->setStoreId($storeId)->setOption($option)->save();

        expect(missingRateOptionValuePriceRow((int) $value->getId(), 0))
            ->toBe(['price' => 50.0, 'price_type' => 'fixed'])
            ->and(missingRateOptionValuePriceRow((int) $value->getId(), $storeId)['price'] ?? 50.0)->toBe(50.0);
    } finally {
        $option->setStoreId(0)->delete();
    }
});

it('derives a custom option price for a store with no row of its own', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();
    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);
    $option = missingRateOption(100.0, 'fixed');

    try {
        expect(missingRateOptionFromCollection((int) $option->getId(), $storeId)->getPrice())
            ->toBe(200.0);
    } finally {
        $option->setStoreId(0)->delete();
    }
});

it('leaves a custom option price the merchant set for the store alone', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();
    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);
    $option = missingRateOption(100.0, 'fixed');

    try {
        $option->setStoreId($storeId)->setPrice(300.0)->setPriceType('fixed')->save();

        expect(missingRateOptionFromCollection((int) $option->getId(), $storeId)->getPrice())
            ->toBe(300.0);
    } finally {
        $option->setStoreId(0)->delete();
    }
});

it('does not convert a percent option price', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();
    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);
    $option = missingRateOption(15.0, 'percent');

    try {
        expect(missingRateOptionFromCollection((int) $option->getId(), $storeId)->getPrice())
            ->toBe(15.0);
    } finally {
        $option->setStoreId(0)->delete();
    }
});

it('offers no custom option price when the store has no rate', function () {
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();
    $option = missingRateOption(100.0, 'fixed');

    try {
        expect(missingRateOptionFromCollection((int) $option->getId(), $storeId)->getPrice())
            ->toBeNull();
    } finally {
        $option->setStoreId(0)->delete();
    }
});

/*
 * Downloadable link prices had the same seeding as the base price, keyed on website rather than
 * store: written once when the link was created, at whatever rate existed then. They are derived
 * on read now. Under test: Mage_Downloadable_Model_Resource_Link.
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

it('writes no website row for a downloadable link price', function () {
    $websiteId = (int) missingRateWebsite()->getId();
    $link = missingRateDownloadableLink();

    try {
        missingRateLinkPriceSave((int) $link->getId(), 100.0, $websiteId);

        expect(missingRateLinkPrices((int) $link->getId()))->toBe([0 => 100.0]);
    } finally {
        $link->delete();
    }
});

it('writes no website row for a downloadable link price even with a rate', function () {
    $websiteId = (int) missingRateWebsite()->getId();

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);
    $link = missingRateDownloadableLink();

    try {
        missingRateLinkPriceSave((int) $link->getId(), 100.0, $websiteId);

        expect(missingRateLinkPrices((int) $link->getId()))->toBe([0 => 100.0]);
    } finally {
        $link->delete();
    }
});

/** Loaded the way the storefront loads them, through the collection that resolves website scope. */
function missingRateLinkFromCollection(int $linkId, int $websiteId, int $storeId): Mage_Downloadable_Model_Link
{
    Mage::app()->setCurrentStore($storeId);

    /** @var Mage_Downloadable_Model_Link $link */
    $link = Mage::getResourceModel('downloadable/link_collection')
        ->addFieldToFilter('main_table.link_id', $linkId)
        ->addPriceToResult($websiteId)
        ->getFirstItem();

    return $link;
}

it('derives a downloadable link price for a website with no row of its own', function () {
    $websiteId = (int) missingRateWebsite()->getId();
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);
    $link = missingRateDownloadableLink();

    try {
        missingRateLinkPriceSave((int) $link->getId(), 100.0, $websiteId);

        expect(missingRateLinkFromCollection((int) $link->getId(), $websiteId, $storeId)->getPrice())
            ->toBe(200.0);
    } finally {
        $link->delete();
    }
});

it('offers no downloadable link price when the website has no rate', function () {
    $websiteId = (int) missingRateWebsite()->getId();
    $storeId = (int) Mage::app()->getStore(MISSING_RATE_CODE)->getId();
    $link = missingRateDownloadableLink();

    try {
        missingRateLinkPriceSave((int) $link->getId(), 100.0, $websiteId);

        expect(missingRateLinkFromCollection((int) $link->getId(), $websiteId, $storeId)->getPrice())
            ->toBeNull();
    } finally {
        $link->delete();
    }
});
