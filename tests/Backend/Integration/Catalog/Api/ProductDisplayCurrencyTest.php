<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Catalog\Api\ProductProvider;
use Maho\ApiPlatform\Service\StoreContext;

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression tests for issue #1238: public product reads must return prices
 * converted to the store display currency (the `currency` field already claims
 * they are), and priceMin/priceMax must be interpreted in that same currency
 * so filters and output agree.
 */

/** Switch store 1 to EUR display currency in-memory and return the USD→EUR rate. */
function catalogUseEurDisplayCurrency(): float
{
    $store = Mage::app()->getStore(1);

    if ($store->getBaseCurrencyCode() !== 'USD') {
        test()->markTestSkipped('Test expects USD base currency on store 1');
    }

    $store->setConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_ALLOW, 'USD,EUR');
    $store->setConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_DEFAULT, 'EUR');
    foreach (['available_currency_codes', 'disallowed_base_currency_code_index', 'current_currency', 'default_currency', 'base_currency'] as $memo) {
        $store->unsetData($memo);
    }

    $rate = (float) $store->getBaseCurrency()->getRate('EUR');
    if ($rate <= 0 || $rate == 1.0) {
        test()->markTestSkipped('USD→EUR rate not available or trivially 1');
    }

    expect($store->getCurrentCurrencyCode())->toBe('EUR');

    return $rate;
}

/** A visible enabled simple product without special pricing, plus its base final price. */
function catalogPickPlainSimpleProduct(): Mage_Catalog_Model_Product
{
    $collection = Mage::getResourceModel('catalog/product_collection')
        ->addWebsiteFilter([1])
        ->addAttributeToSelect(['price', 'special_price'])
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addAttributeToFilter('price', ['gt' => 0])
        ->setVisibility(Mage::getSingleton('catalog/product_visibility')->getVisibleInCatalogIds())
        ->setPageSize(20);

    foreach ($collection as $candidate) {
        if (!$candidate->getSpecialPrice()) {
            return Mage::getModel('catalog/product')->setStoreId(1)->load((int) $candidate->getId());
        }
    }

    test()->markTestSkipped('No plain-priced visible simple product available');
}

describe('Product API display-currency conversion (issue #1238)', function (): void {

    beforeEach(function (): void {
        StoreContext::ensureStore(1);
        $this->rate = catalogUseEurDisplayCurrency();
        $this->provider = new ProductProvider(null);
        Mage::app()->getCache()->clean(['API_PRODUCTS']);
    });

    test('public single-product read converts prices to the display currency', function (): void {
        $product = catalogPickPlainSimpleProduct();
        $basePrice = (float) $product->getPrice();

        $dto = $this->provider->loadProductDto((int) $product->getId());

        expect($dto)->not->toBeNull();
        expect($dto->currency)->toBe('EUR');

        $expected = round($basePrice * $this->rate, 2);
        expect($dto->price)->toEqualWithDelta($expected, 0.011);
        expect($dto->finalPrice)->toEqualWithDelta($expected, 0.011);

        if ($dto->minimalPrice !== null) {
            expect($dto->minimalPrice)->toBeLessThan($basePrice);
        }
    });

    test('priceMin and priceMax are interpreted in the display currency', function (): void {
        $product = catalogPickPlainSimpleProduct();
        $eurPrice = round((float) $product->getFinalPrice() * $this->rate, 2);

        $method = new ReflectionMethod(ProductProvider::class, 'getCollection');
        $paginator = $method->invoke($this->provider, [
            'filters' => [
                'priceMin' => max(0.01, $eurPrice - 0.5),
                'priceMax' => $eurPrice + 0.5,
                'pageSize' => 50,
            ],
        ]);

        $found = null;
        foreach ($paginator as $dto) {
            if ($dto->id === (int) $product->getId()) {
                $found = $dto;
            }
        }

        // The product whose display price sits inside the display-currency
        // bounds must be in the result set, and the price the client sees
        // must itself sit inside the bounds it filtered by.
        expect($found)->not->toBeNull();
        expect($found->price)->toBeGreaterThanOrEqual($eurPrice - 0.51);
        expect($found->price)->toBeLessThanOrEqual($eurPrice + 0.51);
    });

    test('a price band equal to the rounded display price keeps the product', function (): void {
        $product = catalogPickPlainSimpleProduct();
        $eurPrice = round((float) $product->getFinalPrice() * $this->rate, 2);

        $method = new ReflectionMethod(ProductProvider::class, 'getCollection');
        $paginator = $method->invoke($this->provider, [
            'filters' => [
                'priceMin' => $eurPrice,
                'priceMax' => $eurPrice,
                'pageSize' => 50,
            ],
        ]);

        $found = false;
        foreach ($paginator as $dto) {
            if ($dto->id === (int) $product->getId()) {
                $found = true;
            }
        }

        // The display price is rounded after conversion, so a band placed
        // exactly on it must not exclude the product it was read from.
        expect($found)->toBeTrue();
    });

    test('priceMax=0 filters to free products instead of being ignored', function (): void {
        $product = catalogPickPlainSimpleProduct();

        $method = new ReflectionMethod(ProductProvider::class, 'getCollection');
        $paginator = $method->invoke($this->provider, [
            'filters' => ['priceMax' => 0, 'pageSize' => 50],
        ]);

        $ids = [];
        foreach ($paginator as $dto) {
            $ids[] = $dto->id;
        }

        // Zero is a meaningful bound: a priced product must not come back.
        expect($ids)->not->toContain((int) $product->getId());
    });

    test('listing DTOs convert prices to the display currency', function (): void {
        $product = catalogPickPlainSimpleProduct();
        $expected = round((float) $product->getPrice() * $this->rate, 2);

        $method = new ReflectionMethod(ProductProvider::class, 'getCollection');
        $paginator = $method->invoke($this->provider, [
            'filters' => ['attr_sku' => $product->getSku(), 'pageSize' => 10],
        ]);

        $dto = null;
        foreach ($paginator as $candidate) {
            if ($candidate->id === (int) $product->getId()) {
                $dto = $candidate;
            }
        }

        expect($dto)->not->toBeNull();
        expect($dto->currency)->toBe('EUR');
        expect($dto->price)->toEqualWithDelta($expected, 0.011);
    });

});
