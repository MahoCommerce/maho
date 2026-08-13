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
        if (!empty($this->productId)) {
            Mage::getModel('catalog/product')->setId($this->productId)->delete();
        }
        resetCurrencyState();
    });

    test('the preset amounts name the currency they are actually in', function (): void {
        $rate = useEurDisplayCurrency();

        $product = Mage::getModel('catalog/product')
            ->setTypeId('giftcard')
            ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
            ->setWebsiteIds([1])
            ->setName('Gift card amount currency fixture')
            ->setSku('giftcard-amount-currency-' . uniqid())
            ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
            ->setPrice(0)
            ->setData('giftcard_type', 'fixed')
            ->setData('giftcard_amounts', '25,50,100')
            ->setData('giftcard_min_amount', null)
            ->setData('giftcard_max_amount', null)
            ->setData('allow_message', 1)
            ->setData('use_config_is_redeemable', 1)
            ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100]);
        $product->save();
        $this->productId = (int) $product->getId();

        $dto = new Product();
        $enrich = new ReflectionMethod(ProductProvider::class, 'enrichProduct');
        $enrich->invoke(new ProductProvider(), $dto, $product);

        expect($dto->giftcardAmounts)->toBe([25.0, 50.0, 100.0]);

        // The store displays EUR, but these amounts are the add-to-cart round
        // trip and stay in base, so they name base rather than the display code.
        expect($rate)->not->toBe(1.0);
        expect(Mage::app()->getStore(1)->getCurrentCurrencyCode())->toBe('EUR');
        expect($dto->giftcardAmountCurrency)->toBe('USD');
    });

});
