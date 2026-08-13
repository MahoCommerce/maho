<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Order placement converts amounts at the store's live currency but copies the
 * currency code and rate from the quote's stored columns. When the two have
 * drifted, the order is stamped with a currency its amounts are not in, and an
 * order is immutable: invoices, credit memos and reports inherit the error.
 */

function orderStampQuote(Mage_Catalog_Model_Product $product): Mage_Sales_Model_Quote
{
    $quote = Mage::getModel('sales/quote');
    $quote->setStoreId(1);
    $quote->addProduct($product, 2);

    foreach ([$quote->getBillingAddress(), $quote->getShippingAddress()] as $address) {
        $address->setCountryId('US')
            ->setRegionId(12)
            ->setPostcode('90210')
            ->setFirstname('Test')
            ->setLastname('Customer')
            ->setStreet('123 Test St')
            ->setCity('Beverly Hills')
            ->setTelephone('555-1234')
            ->setEmail('order-currency@example.com');
    }
    $quote->getShippingAddress()->setCollectShippingRates(true)->setShippingMethod('flatrate_flatrate');
    $quote->getPayment()->importData(['method' => 'checkmo']);

    $quote->collectTotals();
    $quote->save();

    return $quote;
}

describe('Order currency stamp', function (): void {

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('an order placed without re-saving the quote is stamped in the currency its amounts are in', function (): void {
        $rate = useEurDisplayCurrency();
        $product = loadSimplePricedProduct();
        $basePrice = (float) $product->getFinalPrice();

        $quote = orderStampQuote($product);
        expect($quote->getQuoteCurrencyCode())->toBe('EUR');
        expect((float) $quote->getBaseToQuoteRate())->toEqualWithDelta($rate, 0.0001);

        // The display currency changes after the cart was last saved. The stored
        // columns still say EUR; nothing rewrites them until the quote is saved.
        setStoreDisplayCurrency('USD', 'USD,EUR');

        // The GraphQL cartId-only path: loadAdminQuote() then straight into
        // placeAdminOrder(), with no save to refresh the stored columns.
        $reloaded = Mage::getModel('sales/quote')->loadByIdWithoutStore((int) $quote->getId());
        expect($reloaded->getQuoteCurrencyCode())->toBe('EUR');

        $order = (new Mage\Sales\Api\OrderService())->placeAdminOrder($reloaded)['order'];

        expect($order->getId())->toBeGreaterThan(0);

        // The subtotal came out in USD: base prices, no conversion applied.
        expect((float) $order->getSubtotal())->toEqualWithDelta($basePrice * 2, 0.011);
        expect((float) $order->getSubtotal())
            ->toEqualWithDelta((float) $order->getBaseSubtotal(), 0.011);

        // So the stamp must say USD too. Before the fix it says EUR at 0.85,
        // permanently mislabeling a USD-denominated order.
        expect($order->getOrderCurrencyCode())->toBe('USD');
        expect((float) $order->getBaseToOrderRate())->toEqualWithDelta(1.0, 0.0001);
    });

});
