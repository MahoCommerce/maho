<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartMapper;
use Mage\Checkout\Api\CartService;

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression tests for issue #1238: on a store whose display currency differs
 * from the website base currency, every non-base* money field in the cart API
 * response must be in quote currency. Store 1 is switched to EUR display
 * in-memory (base stays USD); the USD→EUR rate is seeded at install.
 */

/** Switch store 1 to EUR display currency in-memory and return the USD→EUR rate. */
function useEurDisplayCurrency(): float
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

function loadSimplePricedProduct(): Mage_Catalog_Model_Product
{
    // Price >= 10 so ten units always exceed the 50.00 gift card balance and
    // the full balance applies regardless of which sample product is first.
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

function createEurQuoteWithProduct(Mage_Catalog_Model_Product $product, int $qty = 2): Mage_Sales_Model_Quote
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

describe('Cart API quote-currency consistency (issue #1238)', function (): void {

    beforeEach(function (): void {
        $this->rate = useEurDisplayCurrency();
        $this->product = loadSimplePricedProduct();
        $this->mapper = new CartMapper();
    });

    test('item price is in quote currency and consistent with rowTotal', function (): void {
        $quote = createEurQuoteWithProduct($this->product, 2);
        $cart = $this->mapper->mapQuoteToCart($quote, false);

        expect($cart->currency)->toBe('EUR');
        expect($cart->items)->not->toBeEmpty();

        $item = $cart->items[0];
        $basePrice = (float) $this->product->getFinalPrice();
        $expectedUnitPrice = round($basePrice * $this->rate, 2);

        expect($item->price)->toEqualWithDelta($expectedUnitPrice, 0.011);

        // Unit price times qty must land on the row total: one currency per response.
        expect($item->price * $item->qty)->toEqualWithDelta($item->rowTotal, 0.011);

        // The quote-level pair must keep its meaning: base* is base, plain is quote.
        expect($cart->prices['baseSubtotal'])->toEqualWithDelta($basePrice * 2, 0.011);
        expect($cart->prices['subtotal'])->toEqualWithDelta($expectedUnitPrice * 2, 0.011);
    });

    test('available shipping method price matches the selected shipping amount', function (): void {
        $quote = createEurQuoteWithProduct($this->product, 2);
        $quote->getShippingAddress()->setShippingMethod('flatrate_flatrate');
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        $cart = $this->mapper->mapQuoteToCart($quote, false);

        $flatrate = null;
        foreach ($cart->availableShippingMethods as $method) {
            if ($method['code'] === 'flatrate_flatrate') {
                $flatrate = $method;
            }
        }

        if ($flatrate === null || $cart->prices['shippingAmount'] === null) {
            test()->markTestSkipped('Flat rate shipping not available in this environment');
        }

        // The same method must not change price between the list and selection.
        expect($flatrate['price'])->toEqualWithDelta($cart->prices['shippingAmount'], 0.011);
        expect($cart->selectedShippingMethod['price'])->toEqualWithDelta($cart->prices['shippingAmount'], 0.011);

        // And the quote-currency amount differs from base when the rate does.
        expect($cart->prices['baseShippingAmount'])->not->toBeNull();
        expect($flatrate['price'])->toEqualWithDelta($cart->prices['baseShippingAmount'] * $this->rate, 0.011);
    });

    test('applied gift card balance and amount are in quote currency', function (): void {
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode(Mage::helper('giftcard')->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(50.00);
        $giftcard->setInitialBalance(50.00);
        $giftcard->save();

        // A large enough cart that the full balance applies.
        $quote = createEurQuoteWithProduct($this->product, 10);

        $service = new CartService();
        $service->applyGiftcard($quote, $giftcard->getCode());

        // The stored snapshot is base currency: the total collector owns the format.
        $storedCodes = Mage::helper('core')->jsonDecode($quote->getGiftcardCodes(), true);
        expect((float) $storedCodes[$giftcard->getCode()])->toEqualWithDelta(50.00, 0.011);

        $cart = (new CartMapper())->mapQuoteToCart($quote, false);

        expect($cart->appliedGiftcards)->toHaveCount(1);
        $applied = $cart->appliedGiftcards[0];

        $expectedEur = round(50.00 * $this->rate, 2);

        expect((float) $applied['balance'])->toEqualWithDelta($expectedEur, 0.011);
        expect((float) $applied['appliedAmount'])->toEqualWithDelta($expectedEur, 0.011);
        expect((float) $cart->prices['giftcardAmount'])->toEqualWithDelta($expectedEur, 0.011);

        // appliedAmount must agree with the amount actually subtracted from totals.
        expect((float) $applied['appliedAmount'])->toEqualWithDelta((float) $cart->prices['giftcardAmount'], 0.011);
    });

});
