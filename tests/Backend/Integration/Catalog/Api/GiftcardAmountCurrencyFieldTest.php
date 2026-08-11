<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Catalog\Api\Product;
use Mage\Catalog\Api\ProductProvider;

uses(Tests\MahoBackendTestCase::class);

/**
 * The gift card preset amounts are the add-to-cart round trip, so they stay in
 * base currency while the product's prices follow the display one. A client
 * rendering money by the DTO's `currency` field would get them wrong, so they
 * name their own.
 */

describe('Gift card amount currency field', function (): void {

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('the preset amounts name the currency they are actually in', function (): void {
        $rate = useEurDisplayCurrency();

        $productId = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('type_id', 'giftcard')
            ->setPageSize(1)
            ->getFirstItem()
            ->getId();

        if (!$productId) {
            test()->markTestSkipped('No gift card product available');
        }

        $product = Mage::getModel('catalog/product')->setStoreId(1)->load($productId);
        if (!$product->getData('giftcard_amounts')) {
            test()->markTestSkipped('Gift card product carries no preset amounts');
        }

        $dto = new Product();
        $enrich = new ReflectionMethod(ProductProvider::class, 'enrichProduct');
        $enrich->invoke(new ProductProvider(), $dto, $product);

        expect($dto->giftcardAmounts)->not->toBeEmpty();

        // The store displays EUR, but these amounts are the add-to-cart round
        // trip and stay in base, so they name base rather than the display code.
        expect($rate)->not->toBe(1.0);
        expect(Mage::app()->getStore(1)->getCurrentCurrencyCode())->toBe('EUR');
        expect($dto->giftcardAmountCurrency)->toBe('USD');
    });

});
