<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartMapper;

uses(Tests\MahoBackendTestCase::class);

/**
 * The cart API recomputes every amount on read, so the `currency` field has to
 * name the currency they were computed in, not the code stamped on the row.
 */

function cartLabelPickProduct(): Mage_Catalog_Model_Product
{
    $productId = Mage::getResourceModel('catalog/product_collection')
        ->addWebsiteFilter([1])
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addAttributeToFilter('price', ['gteq' => 10])
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();

    if (!$productId) {
        test()->markTestSkipped('No priced simple product available');
    }

    return Mage::getModel('catalog/product')->setStoreId(1)->load($productId);
}

function cartLabelSaveQuote(Mage_Catalog_Model_Product $product, int $qty = 2): Mage_Sales_Model_Quote
{
    $quote = Mage::getModel('sales/quote');
    $quote->setStoreId(1);
    $quote->addProduct($product, $qty);
    $quote->getShippingAddress()
        ->setCountryId('US')
        ->setRegionId(12)
        ->setPostcode('90210')
        ->setCollectShippingRates(true);
    $quote->collectTotals();
    $quote->save();

    return $quote;
}

describe('Cart API currency label', function (): void {

    test('the label follows a display currency change, because the amounts do', function (): void {
        $rate = useEurDisplayCurrency();
        $product = cartLabelPickProduct();
        $basePrice = (float) $product->getFinalPrice();

        $quote = cartLabelSaveQuote($product, 2);
        expect($quote->getQuoteCurrencyCode())->toBe('EUR');
        expect((float) $quote->getBaseToQuoteRate())->toEqualWithDelta($rate, 0.0001);

        setStoreDisplayCurrency('USD', 'USD,EUR');

        $reloaded = Mage::getModel('sales/quote')->setStoreId(1)->load((int) $quote->getId());
        expect($reloaded->getQuoteCurrencyCode())->toBe('EUR');

        $cart = (new CartMapper())->mapQuoteToCart($reloaded, true);

        expect($cart->prices['subtotal'])->toEqualWithDelta($basePrice * 2, 0.011);
        expect($cart->items[0]->price)->toEqualWithDelta($basePrice, 0.011);
        expect($cart->currency)->toBe('USD');
    });

    test('the label is the base currency when the display currency has no rate', function (): void {
        $rate = useEurDisplayCurrency();
        $store = Mage::app()->getStore(1);

        $product = cartLabelPickProduct();
        $basePrice = (float) $product->getFinalPrice();

        $quote = cartLabelSaveQuote($product, 2);
        expect($quote->getQuoteCurrencyCode())->toBe('EUR');
        expect((float) $quote->getSubtotal())->toEqualWithDelta($basePrice * 2 * $rate, 0.011);

        setStoreDisplayCurrency('GBP', 'USD,EUR,GBP');
        if ((float) $store->getBaseCurrency()->getRate('GBP') > 0) {
            test()->markTestSkipped('This install has a USD to GBP rate, so there is no fallback to observe');
        }

        expect($store->getCurrentCurrencyCode())->toBe('GBP');
        expect($store->getCurrentCurrency()->getCode())->toBe('USD');

        $reloaded = Mage::getModel('sales/quote')->setStoreId(1)->load((int) $quote->getId());
        $cart = (new CartMapper())->mapQuoteToCart($reloaded, true);

        expect($cart->prices['subtotal'])->toEqualWithDelta($basePrice * 2, 0.011);
        expect($cart->currency)->toBe('USD');
    });

});
