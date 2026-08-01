<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

describe('GA4 purchase event', function () {
    beforeEach(function () {
        $this->block = new Mage_GoogleAnalytics_Block_Ga();
    });

    it('sends the catalog product SKU and tax-inclusive prices', function () {
        $product = Mage::getModel('catalog/product')
            ->setSku('basesku123')
            ->setCategoryIds([]);
        $item = Mage::getModel('sales/order_item')
            ->setSku('basesku123-custom option')
            ->setName('Base product')
            ->setQtyOrdered(2)
            ->setBasePrice(100.00)
            ->setBasePriceInclTax(122.00)
            ->setBaseDiscountAmount(0.00)
            ->setProduct($product);
        $order = Mage::getModel('sales/order')
            ->setBaseGrandTotal(254.00)
            ->setBaseCurrencyCode('EUR')
            ->setBaseShippingAmount(10.00)
            ->setBaseTaxAmount(44.00)
            ->setIncrementId('100000042');
        $order->addItem($item);

        $orderData = $this->block->getPurchaseEventData($order);

        expect($orderData['transaction_id'])->toBe('100000042');
        expect($orderData['currency'])->toBe('EUR');
        expect($orderData['value'])->toBe('254.00');
        expect($orderData['shipping'])->toBe('10.00');
        expect($orderData['tax'])->toBe('44.00');
        expect($orderData['items'][0]['item_id'])->toBe('basesku123');
        expect($orderData['items'][0]['item_name'])->toBe('Base product');
        expect($orderData['items'][0]['quantity'])->toBe(2);
        expect($orderData['items'][0]['price'])->toBe('122.00');
    });

    it('falls back to the order item SKU when the product no longer exists', function () {
        $item = Mage::getModel('sales/order_item')
            ->setSku('deleted-sku')
            ->setQtyOrdered(1)
            ->setBasePriceInclTax(10.00)
            ->setProduct(Mage::getModel('catalog/product')->setCategoryIds([]));
        $order = Mage::getModel('sales/order')->setBaseGrandTotal(10.00);
        $order->addItem($item);

        expect($this->block->getPurchaseEventData($order)['items'][0]['item_id'])->toBe('deleted-sku');
    });
});
