<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function renderPriceTemplate(float $price): string
{
    $store = Mage::app()->getStore();
    $store->setConfig('tax/calculation/price_includes_tax', 0);
    $store->setConfig('tax/display/type', (string) Mage_Tax_Model_Config::DISPLAY_TYPE_INCLUDING_TAX);

    $product = Mage::getModel('catalog/product')
        ->setId(1)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setStoreId($store->getId())
        ->setPrice($price)
        ->setFinalPrice($price)
        ->setTaxPercent(22)
        ->setAppliedRates([['percent' => 22]]);

    return Mage::app()->getLayout()->createBlock('catalog/product_price')
        ->setTemplate('catalog/product/price.phtml')
        ->setProduct($product)
        ->toHtml();
}

function formattedPrice(float $price): string
{
    return trim(strip_tags(Mage::helper('core')->formatPrice($price, false)));
}

describe('catalog/product/price.phtml tax rounding', function () {
    it('adds tax to the full 4-decimal price instead of the price rounded to 2 decimals', function () {
        $html = renderPriceTemplate(10.6557);

        expect($html)->toContain(formattedPrice(13.00));
        expect($html)->not->toContain(formattedPrice(13.01));
    });

    it('keeps prices with 2 decimals unchanged', function () {
        $html = renderPriceTemplate(10.66);

        expect($html)->toContain(formattedPrice(13.01));
    });
});
