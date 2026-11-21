<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('recalculates the cart total after a product is disabled', function (string $removedType): void {
    $app = Mage::app();
    $store = $app->getDefaultStoreView();
    $storeId = (int) $store->getId();
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $adapter->beginTransaction();
    try {
        $app->withStore($storeId, function () use ($store, $storeId, $removedType): void {
            $cart = Mage::getService('checkout/cart');
            $quote = $cart->create(null, $storeId);
            $products = [];
            foreach ([[15.0, Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL], [20.0, $removedType]] as [$price, $type]) {
                $product = Mage::getModel('catalog/product')->setStoreId(0);
                $product->setAttributeSetId($product->getDefaultAttributeSetId())
                    ->setTypeId($type)
                    ->setSku('quote-total-' . uniqid())
                    ->setName('Cart total test item')
                    ->setPrice($price)
                    ->setWeight(1.0)
                    ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                    ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
                    ->setTaxClassId(0)
                    ->setWebsiteIds([(int) $store->getWebsiteId()])
                    ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100])
                    ->save();
                $products[] = $product;
                $storeProduct = Mage::getModel('catalog/product')->setStoreId($storeId)->load($product->getId());
                $cart->addProduct($quote, $storeProduct, new \Maho\DataObject(['qty' => 1]), save: false);
            }
            $cart->collectAndSave($quote);
            expect($quote->getBaseGrandTotal())->toBe(35.0);

            [$kept, $removed] = $products;
            Mage::getModel('catalog/product')->setStoreId(0)->load($removed->getId())
                ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_DISABLED)->save();
            $quote = Mage::getModel('sales/quote')->setStoreId($storeId)->load($quote->getId());
            $cart->collectAndSave($quote);

            expect(array_map(fn(Mage_Sales_Model_Quote_Item $item): ?int => $item->getProductId(), $quote->getAllVisibleItems()))
                ->toBe([$kept->getId()])
                ->and($quote->getBaseGrandTotal())->toBe(15.0)
                ->and($quote->getBaseSubtotal())->toBe(15.0);
        });
    } finally {
        $adapter->rollBack();
    }
})->with([
    'virtual product' => [Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL],
    'physical product' => [Mage_Catalog_Model_Product_Type::TYPE_SIMPLE],
]);
