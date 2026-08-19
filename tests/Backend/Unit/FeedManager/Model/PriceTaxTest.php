<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

$taxConfigPaths = [
    'tax/calculation/price_includes_tax',
    'tax/calculation/cross_border_trade_enabled',
];
$originalTaxConfig = [];

beforeEach(function () use ($taxConfigPaths, &$originalTaxConfig): void {
    $store = Mage::app()->getStore(1);
    foreach ($taxConfigPaths as $path) {
        $originalTaxConfig[$path] = $store->getConfig($path);
    }
});

afterEach(function () use (&$originalTaxConfig): void {
    $store = Mage::app()->getStore(1);
    foreach ($originalTaxConfig as $path => $value) {
        $store->setConfig($path, $value);
    }
});

/** A feed is enough for the adjuster: it reads the tax mode and the store only. */
function taxFeed(string $taxMode): Maho_FeedManager_Model_Feed
{
    return Mage::getModel('feedmanager/feed')
        ->setPlatform('custom')
        ->setStoreId(1)
        ->setTaxMode($taxMode);
}

/** A product carrying a single 22 percent rate, so the adjuster never queries a rate. */
function taxProduct(float $price = 20.00, ?int $taxClassId = 2): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product')
        ->setId(1)
        ->setTypeId('simple')
        ->setStoreId(1)
        ->setPrice($price);

    if ($taxClassId !== null) {
        $product->setTaxClassId($taxClassId)->setTaxPercent(22);
    }

    return $product;
}

describe('Feed tax mode', function () {
    it('adds tax when the store stores prices without tax and the feed asks for tax', function () {
        Mage::app()->getStore(1)->setConfig('tax/calculation/price_includes_tax', 0);

        $adjuster = new Maho_FeedManager_Model_Price_TaxAdjuster(taxFeed('incl'));

        expect($adjuster->adjust(taxProduct(), 20.00))->toBe(24.40);
    });

    it('leaves the price alone when the feed asks for the mode the store already stores', function () {
        Mage::app()->getStore(1)->setConfig('tax/calculation/price_includes_tax', 0);

        $adjuster = new Maho_FeedManager_Model_Price_TaxAdjuster(taxFeed('excl'));

        expect($adjuster->adjust(taxProduct(), 20.00))->toBe(20.00);
    });

    it('removes tax when the store stores prices with tax and the feed asks for none', function () {
        $store = Mage::app()->getStore(1);
        $store->setConfig('tax/calculation/price_includes_tax', 1);
        $store->setConfig('tax/calculation/cross_border_trade_enabled', 1);

        $adjuster = new Maho_FeedManager_Model_Price_TaxAdjuster(taxFeed('excl'));

        expect($adjuster->adjust(taxProduct(), 24.40))->toBe(20.00);
    });

    it('keeps a tax inclusive price when the feed asks for tax', function () {
        Mage::app()->getStore(1)->setConfig('tax/calculation/price_includes_tax', 1);

        $adjuster = new Maho_FeedManager_Model_Price_TaxAdjuster(taxFeed('incl'));

        expect($adjuster->adjust(taxProduct(), 24.40))->toBe(24.40);
    });

    it('treats a missing tax mode as "include tax"', function () {
        Mage::app()->getStore(1)->setConfig('tax/calculation/price_includes_tax', 0);

        $feed = Mage::getModel('feedmanager/feed')->setPlatform('custom')->setStoreId(1);
        $adjuster = new Maho_FeedManager_Model_Price_TaxAdjuster($feed);

        expect($adjuster->adjust(taxProduct(), 20.00))->toBe(24.40);
    });

    it('leaves a product without a tax class alone', function () {
        Mage::app()->getStore(1)->setConfig('tax/calculation/price_includes_tax', 0);

        $adjuster = new Maho_FeedManager_Model_Price_TaxAdjuster(taxFeed('incl'));

        expect($adjuster->adjust(taxProduct(20.00, null), 20.00))->toBe(20.00);
    });

    it('returns null for a price that is not a number', function () {
        Mage::app()->getStore(1)->setConfig('tax/calculation/price_includes_tax', 0);

        $adjuster = new Maho_FeedManager_Model_Price_TaxAdjuster(taxFeed('incl'));

        expect($adjuster->adjust(taxProduct(), null))->toBeNull();
        expect($adjuster->adjust(taxProduct(), ''))->toBeNull();
    });
});
