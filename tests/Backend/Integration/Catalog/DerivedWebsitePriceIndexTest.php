<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A website whose base currency differs from the default one sells at the default-scope price
 * converted at the rate. The conversion happens when the index is built, not once at product
 * creation, so it follows the rate. The currency is an ISO 4217 "X" code no real currency uses.
 */
const DERIVED_INDEX_CODE = 'derived_price_ws';
const DERIVED_INDEX_CURRENCY = 'XTD';
const DERIVED_INDEX_PRICE = 10.6557;
const DERIVED_INDEX_RATE = 0.85;
// 10.6557 * 0.85 = 9.057345, held at the four decimals the index and the price column store
const DERIVED_INDEX_EXPECTED = 9.0573;

function derivedIndexWebsite(): Mage_Core_Model_Website
{
    return createPriceWebsite(DERIVED_INDEX_CODE, 97);
}

function derivedIndexConfigure(): void
{
    configurePriceWebsite(DERIVED_INDEX_CODE, DERIVED_INDEX_CURRENCY);
}

function derivedIndexRestore(): void
{
    restorePriceScope(DERIVED_INDEX_CODE);
}

function derivedIndexDeleteWebsite(): void
{
    deletePriceWebsite(DERIVED_INDEX_CODE);
}

function derivedIndexDropRate(): void
{
    dropCurrencyRates(DERIVED_INDEX_CURRENCY);
}

/**
 * The rate lives in the website date table, which a full reindex and a product save rebuild but
 * reindexProductIds() does not. Rebuilt here so the partial reindex below sees the current rate.
 */
function derivedIndexPrepareRates(): void
{
    $resource = Mage::getResourceSingleton('catalog/product_indexer_price');
    (new ReflectionMethod($resource, '_prepareWebsiteDateTable'))->invoke($resource);
}
function derivedIndexProduct(): Mage_Catalog_Model_Product
{
    return createPriceWebsiteProduct('derived-index', DERIVED_INDEX_PRICE, derivedIndexWebsite());
}

/** @return array<int, float> website id to the indexed price */
function derivedIndexPrices(int $productId): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $rows = $adapter->fetchAll(
        $adapter->select()
            ->from($resource->getTableName('catalog/product_index_price'), ['website_id', 'price'])
            ->where('entity_id = ?', $productId)
            ->order('website_id ASC'),
    );

    $prices = [];
    foreach ($rows as $row) {
        $prices[(int) $row['website_id']] = (float) $row['price'];
    }

    return $prices;
}

beforeEach(function () {
    derivedIndexWebsite();
    derivedIndexConfigure();
    $this->product = derivedIndexProduct();
});

afterEach(function () {
    if (isset($this->product) && $this->product->getId()) {
        $this->product->delete();
    }
    derivedIndexRestore();
    derivedIndexDropRate();
    derivedIndexDeleteWebsite();
});

/**
 * A downloadable product prices its links separately, and those prices live in a table of their
 * own keyed by website. The index has to derive them the same way the model does.
 */
function derivedIndexDownloadableProduct(): Mage_Catalog_Model_Product
{
    /** @var Mage_Catalog_Model_Product $product */
    $product = Mage::getModel('catalog/product');
    $product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
        ->setSku('derived-index-dl-' . uniqid())
        ->setName('Derived Index Downloadable')
        ->setPrice(DERIVED_INDEX_PRICE)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTypeId(Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE)
        ->setAttributeSetId(4)
        ->setLinksPurchasedSeparately(1)
        ->setWebsiteIds([1, (int) derivedIndexWebsite()->getId()])
        ->save();

    /** @var Mage_Downloadable_Model_Link $link */
    $link = Mage::getModel('downloadable/link');
    $link->setProductId((int) $product->getId())
        ->setSortOrder(0)
        ->setNumberOfDownloads(0)
        ->setIsShareable(2)
        ->setLinkType('url')
        ->setLinkUrl('https://example.com/derived-index')
        ->setUseDefaultTitle(true)
        ->setUseDefaultPrice(false)
        ->setStoreId(0)
        ->setWebsiteId(0)
        ->setPrice(20.0)
        ->save();

    Mage::getResourceSingleton('downloadable/link')->saveItemTitleAndPrice($link);

    return $product;
}

it('derives a downloadable link price in the index', function () {
    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [DERIVED_INDEX_CURRENCY => DERIVED_INDEX_RATE],
    ]);

    $product = derivedIndexDownloadableProduct();
    $websiteId = (int) derivedIndexWebsite()->getId();

    try {
        derivedIndexPrepareRates();
        Mage::getResourceSingleton('catalog/product_indexer_price')
            ->reindexProductIds([(int) $product->getId()]);

        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $prices = $adapter->fetchPairs(
            $adapter->select()
                ->from($resource->getTableName('catalog/product_index_price'), ['website_id', 'min_price'])
                ->where('entity_id = ?', (int) $product->getId()),
        );

        // 10.6557 * 0.85 for the product plus 20.0 * 0.85 for the link
        expect((float) $prices[$websiteId])->toBe(round(DERIVED_INDEX_EXPECTED + 17.0, 4));
        expect((float) $prices[1])->toBe(DERIVED_INDEX_PRICE + 20.0);
    } finally {
        $product->delete();
    }
});

/** @return array<int, float> website id to the indexed maximal price */
function derivedIndexMaxPrices(int $productId): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $prices = $adapter->fetchPairs(
        $adapter->select()
            ->from($resource->getTableName('catalog/product_index_price'), ['website_id', 'max_price'])
            ->where('entity_id = ?', $productId)
            ->where('customer_group_id = ?', 0),
    );

    return array_map('floatval', $prices);
}

/*
 * A custom option with a fixed price is part of the maximal price the index advertises. That
 * amount is stored in the default currency like the product price, so the index converts it the
 * same way, or the listing would show a price the product page never charges.
 */
it('derives a required custom option price in the index', function () {
    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [DERIVED_INDEX_CURRENCY => DERIVED_INDEX_RATE],
    ]);
    $option = Mage::getModel('catalog/product_option')
        ->setProductId((int) $this->product->getId())
        ->setStoreId(0)
        ->setType(Mage_Catalog_Model_Product_Option::OPTION_TYPE_FIELD)
        ->setIsRequire(1)
        ->setSortOrder(0)
        ->setTitle('Engraving')
        ->setPrice(10.0)
        ->setPriceType('fixed')
        ->save();

    try {
        derivedIndexPrepareRates();
        Mage::getResourceSingleton('catalog/product_indexer_price')
            ->reindexProductIds([(int) $this->product->getId()]);

        $prices = derivedIndexMaxPrices((int) $this->product->getId());
        expect($prices[(int) derivedIndexWebsite()->getId()] ?? null)
            ->toEqualWithDelta(DERIVED_INDEX_EXPECTED + round(10.0 * DERIVED_INDEX_RATE, 4), 0.0001)
            ->and($prices[1] ?? null)->toEqualWithDelta(DERIVED_INDEX_PRICE + 10.0, 0.0001);
    } finally {
        $option->setStoreId(0)->delete();
    }
});

it('indexes a website base price converted at the rate', function () {
    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [DERIVED_INDEX_CURRENCY => DERIVED_INDEX_RATE],
    ]);

    derivedIndexPrepareRates();

    Mage::getResourceSingleton('catalog/product_indexer_price')
        ->reindexProductIds([(int) $this->product->getId()]);

    $prices = derivedIndexPrices((int) $this->product->getId());
    $websiteId = (int) derivedIndexWebsite()->getId();

    expect($prices)->toHaveKey($websiteId);
    expect($prices[$websiteId])->toBe(DERIVED_INDEX_EXPECTED);
    expect($prices[1])->toBe(DERIVED_INDEX_PRICE);
});

function derivedIndexWriteStorePrice(int $productId, int $storeId, float $value): void
{
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'price');
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->insert($attribute->getBackend()->getTable(), [
        'attribute_id' => (int) $attribute->getId(),
        'store_id'     => $storeId,
        'entity_id'    => $productId,
        'value'        => $value,
    ]);
}

/*
 * An explicit website price is a real price, but the website still has no rate for anything it
 * derives, and the model refuses to sell there at all. The index has to say the same.
 */
it('leaves a website with no rate out of the price index even with an explicit price', function () {
    derivedIndexWriteStorePrice(
        (int) $this->product->getId(),
        (int) Mage::app()->getStore(DERIVED_INDEX_CODE)->getId(),
        12.0,
    );

    derivedIndexPrepareRates();

    Mage::getResourceSingleton('catalog/product_indexer_price')
        ->reindexProductIds([(int) $this->product->getId()]);

    $prices = derivedIndexPrices((int) $this->product->getId());

    expect($prices)->not->toHaveKey((int) derivedIndexWebsite()->getId());
    expect($prices[1])->toBe(DERIVED_INDEX_PRICE);
});

it('leaves a website with no rate out of the price index', function () {
    derivedIndexPrepareRates();

    Mage::getResourceSingleton('catalog/product_indexer_price')
        ->reindexProductIds([(int) $this->product->getId()]);

    $prices = derivedIndexPrices((int) $this->product->getId());

    expect($prices)->not->toHaveKey((int) derivedIndexWebsite()->getId());
    expect($prices[1])->toBe(DERIVED_INDEX_PRICE);
});
