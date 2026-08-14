<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

/** Prices are stored excluding tax and displayed including tax, the setup that exposed the bug. */
$vatStore = static function (): Mage_Core_Model_Store {
    $store = Mage::app()->getStore();
    $store->setConfig('tax/calculation/price_includes_tax', 0);
    $store->setConfig('tax/display/type', (string) Mage_Tax_Model_Config::DISPLAY_TYPE_INCLUDING_TAX);

    return $store;
};

/** A product carrying a single 22% rate, so the templates never hit the database for one. */
$vatProduct = static function (Mage_Core_Model_Store $store, string $typeId, array $data): Mage_Catalog_Model_Product {
    return Mage::getModel('catalog/product')
        ->setId(1)
        ->setTypeId($typeId)
        ->setStoreId($store->getId())
        ->setTaxPercent(22)
        ->setAppliedRates([['percent' => 22]])
        ->addData($data);
};

$render = static function (string $blockAlias, string $template, Mage_Catalog_Model_Product $product, array $blockData = []): string {
    return Mage::app()->getLayout()->createBlock($blockAlias)
        ->addData($blockData)
        ->setTemplate($template)
        ->setProduct($product)
        ->toHtml();
};

$money = static fn(float $price): string => trim(strip_tags(Mage::helper('core')->formatPrice($price, false)));

describe('catalog price templates tax rounding', function () use ($vatStore, $vatProduct, $render, $money) {
    it('adds tax to the full 4-decimal price instead of the price rounded to 2 decimals', function () use ($vatStore, $vatProduct, $render, $money) {
        $store = $vatStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.6557,
            'final_price' => 10.6557,
        ]);

        $html = $render('catalog/product_price', 'catalog/product/price.phtml', $product);

        expect($html)->toContain($money(13.00))->not->toContain($money(13.01));
    });

    it('keeps prices with 2 decimals unchanged', function () use ($vatStore, $vatProduct, $render, $money) {
        $store = $vatStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.66,
            'final_price' => 10.66,
        ]);

        $html = $render('catalog/product_price', 'catalog/product/price.phtml', $product);

        expect($html)->toContain($money(13.01));
    });

    it('applies the same rounding in the rss price template', function () use ($vatStore, $vatProduct, $render, $money) {
        $store = $vatStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.6557,
            'final_price' => 10.6557,
        ]);

        $html = $render('catalog/product_price', 'catalog/rss/product/price.phtml', $product);

        expect($html)->toContain($money(13.00))->not->toContain($money(13.01));
    });

    it('applies the same rounding in the gift card price template', function () use ($vatStore, $vatProduct, $render, $money) {
        $store = $vatStore();
        $product = $vatProduct($store, 'giftcard', [
            'giftcard_type' => 'fixed',
            'giftcard_amounts' => '10.6557,25',
        ]);

        $html = $render('giftcard/catalog_product_price', 'maho/giftcard/catalog/product/price.phtml', $product);

        expect($html)->toContain($money(13.00))->not->toContain($money(13.01));
    });
});

describe('catalog/product/price.phtml minimal price visibility', function () use ($vatStore, $vatProduct, $render) {
    it('hides the "From" price when it rounds to the same amount as the final price', function () use ($vatStore, $vatProduct, $render) {
        $store = $vatStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.66,
            'final_price' => 10.66,
            'minimal_price' => 10.6557,
        ]);

        $html = $render('catalog/product_price', 'catalog/product/price.phtml', $product, [
            'display_minimal_price' => true,
        ]);

        expect($html)->not->toContain('minimal-price-link');
    });

    it('shows the "From" price when it is genuinely lower', function () use ($vatStore, $vatProduct, $render) {
        $store = $vatStore();
        $product = $vatProduct($store, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, [
            'price' => 10.66,
            'final_price' => 10.66,
            'minimal_price' => 5.00,
        ]);

        $html = $render('catalog/product_price', 'catalog/product/price.phtml', $product, [
            'display_minimal_price' => true,
        ]);

        expect($html)->toContain('minimal-price-link');
    });
});
