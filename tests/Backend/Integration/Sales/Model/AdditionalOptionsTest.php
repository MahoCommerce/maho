<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function additionalOptionsProduct(): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product');
    $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
    $product->setId(1);
    return $product;
}

function additionalOptionsNote(): array
{
    return [['label' => 'Note', 'value' => 'Hello']];
}

describe('Mage_Catalog_Model_Product_Type_Abstract::getOrderOptions()', function (): void {
    test('copies a JSON encoded additional_options custom option', function (): void {
        $product = additionalOptionsProduct();
        $product->addCustomOption('additional_options', Mage::helper('core')->jsonEncode(additionalOptionsNote()));

        $options = $product->getTypeInstance(true)->getOrderOptions($product);

        expect($options)->toHaveKey('additional_options');
        expect($options['additional_options'])->toBe(additionalOptionsNote());
    });

    test('copies a legacy PHP serialized additional_options custom option', function (): void {
        $product = additionalOptionsProduct();
        $product->addCustomOption('additional_options', serialize(additionalOptionsNote()));

        $options = $product->getTypeInstance(true)->getOrderOptions($product);

        expect($options['additional_options'])->toBe(additionalOptionsNote());
    });

    test('omits the key when the value does not decode to a list', function (): void {
        $product = additionalOptionsProduct();
        $product->addCustomOption('additional_options', 'not-a-serialized-array');

        $options = $product->getTypeInstance(true)->getOrderOptions($product);

        expect($options)->not->toHaveKey('additional_options');
    });

    test('omits the key when the value decodes to an empty array', function (): void {
        $product = additionalOptionsProduct();
        $product->addCustomOption('additional_options', Mage::helper('core')->jsonEncode([]));

        $options = $product->getTypeInstance(true)->getOrderOptions($product);

        expect($options)->not->toHaveKey('additional_options');
    });
});

describe('Quote item to order item conversion', function (): void {
    test('carries additional_options into the order item product_options', function (): void {
        $product = additionalOptionsProduct();
        $product->setSku('additional-options-fixture');
        $product->setName('Additional Options Fixture');

        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);

        $quoteItem = Mage::getModel('sales/quote_item');
        $quoteItem->setQuote($quote);
        $quoteItem->setData('product', $product);
        $quoteItem->setProductId($product->getId());
        $quoteItem->setProductType($product->getTypeId());
        $quoteItem->setData('qty', 1);
        $quoteItem->addOption([
            'product_id' => $product->getId(),
            'code' => 'additional_options',
            'value' => Mage::helper('core')->jsonEncode(additionalOptionsNote()),
        ]);

        $orderItem = Mage::getModel('sales/convert_quote')->itemToOrderItem($quoteItem);

        expect($orderItem->getProductOptionByCode('additional_options'))->toBe(additionalOptionsNote());
    });
});

describe('Order item display', function (): void {
    test('renders additional_options in the order item renderer', function (): void {
        $orderItem = Mage::getModel('sales/order_item');
        $orderItem->setProductOptions(['additional_options' => additionalOptionsNote()]);

        $block = Mage::app()->getLayout()->createBlock('sales/order_item_renderer_default');
        $block->setItem($orderItem);

        expect($block->getItemOptions())->toBe(additionalOptionsNote());
    });

    test('ignores a legacy row that stored a scalar instead of a list', function (): void {
        $orderItem = Mage::getModel('sales/order_item');
        $orderItem->setProductOptions(['additional_options' => 'legacy scalar']);

        $block = Mage::app()->getLayout()->createBlock('sales/order_item_renderer_default');
        $block->setItem($orderItem);

        expect($block->getItemOptions())->toBe([]);
    });

    test('exposes additional_options through the order item accessor', function (): void {
        $orderItem = Mage::getModel('sales/order_item');
        $orderItem->setProductOptions(['additional_options' => additionalOptionsNote()]);

        expect($orderItem->getProductAdditionalOptions())->toBe(additionalOptionsNote());
    });

    test('returns an empty list when the order item has no additional_options', function (): void {
        $orderItem = Mage::getModel('sales/order_item');

        expect($orderItem->getProductAdditionalOptions())->toBe([]);
    });
});
