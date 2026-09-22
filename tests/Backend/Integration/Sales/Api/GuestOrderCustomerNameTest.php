<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Sales\Api\OrderService;

uses(Tests\MahoBackendTestCase::class);

/**
 * An API cart holds no customer name unless the client assigned a customer to it,
 * and sales_convert_quote copies that empty name to the order (issue #1408).
 */
describe('guest order customer name', function (): void {

    it('copies the billing address name onto the order', function (): void {
        $product = loadSimplePricedProduct();
        $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
        $stockQty = (float) $stock->getQty();
        $stockIsIn = (bool) $stock->getIsInStock();

        try {
            $quote = createPlaceableQuote($product, 1);
            $quote->getBillingAddress()
                ->setPrefix('Dr')
                ->setFirstname('Jane')
                ->setMiddlename('Q')
                ->setLastname('Smith')
                ->setSuffix('Jr');
            $quote->setCustomerIsGuest()->save();

            // Place-order is its own request, so it reads the quote back from the database.
            $placed = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());
            if (!$placed->getShippingAddress()->getShippingMethod()) {
                test()->markTestSkipped('Flat rate shipping not available in this environment');
            }

            $order = (new OrderService())->placeAdminOrder($placed, 'jane@example.com')['order'];

            expect($order->getCustomerFirstname())->toBe('Jane');
            expect($order->getCustomerLastname())->toBe('Smith');
            expect($order->getCustomerPrefix())->toBe('Dr');
            expect($order->getCustomerMiddlename())->toBe('Q');
            expect($order->getCustomerSuffix())->toBe('Jr');
            expect($order->getCustomerName())->toContain('Jane');
            expect($order->getCustomerName())->toContain('Smith');
        } finally {
            $stock->setQty($stockQty)->setIsInStock($stockIsIn)->save();
        }
    });

});
