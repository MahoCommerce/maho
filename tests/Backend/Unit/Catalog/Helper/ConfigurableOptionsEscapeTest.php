<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function rawAttributesInfo(): array
{
    return [
        ['label' => 'Color', 'value' => '<img src=x onerror=alert(1)>'],
        ['label' => 'Size', 'value' => 'Black &amp; "White"'],
    ];
}

it('escapes the option values of configurable attributes and keeps the labels', function (): void {
    $escaped = Mage::helper('catalog/product_configuration')->escapeAttributesInfo(rawAttributesInfo());

    expect($escaped)->toBe([
        ['label' => 'Color', 'value' => '&lt;img src=x onerror=alert(1)&gt;'],
        ['label' => 'Size', 'value' => 'Black &amp; &quot;White&quot;'],
    ]);
});

it('escapes the configurable option labels that an order keeps', function (string $blockClass): void {
    $orderItem = Mage::getModel('sales/order_item')->setProductOptions(['attributes_info' => rawAttributesInfo()]);
    $item = $blockClass === Mage_Sales_Block_Order_Item_Renderer_Default::class
        ? $orderItem
        : Mage::getModel('sales/order_invoice_item')->setOrderItem($orderItem);
    $block = new $blockClass();
    $block->setItem($item);

    expect(array_column($block->getItemOptions(), 'value'))->toBe([
        '&lt;img src=x onerror=alert(1)&gt;',
        'Black &amp; &quot;White&quot;',
    ]);
})->with([
    Mage_Sales_Block_Order_Item_Renderer_Default::class,
    Mage_Sales_Block_Order_Email_Items_Default::class,
]);

it('does not cut an HTML entity when it truncates an option value', function (string $value, string $expected): void {
    $block = new Mage_Sales_Block_Order_Item_Renderer_Default();
    $catalogResult = Mage::helper('catalog/product_configuration')->getFormattedOptionValue($value, ['max_length' => 55, 'cut_replacer' => '']);

    expect($block->getFormatedOptionValue($value)['value'])->toBe($expected . ' <a href="#" class="dots" onclick="return false">...</a>')
        ->and($catalogResult['value'])->toBe($expected)
        ->and($catalogResult['full_view'])->toBe($value);
})->with([
    'quotation mark at the boundary' => [str_repeat('a', 52) . '&quot;b', str_repeat('a', 52)],
    'ampersand at the boundary' => [str_repeat('a', 53) . '&amp;b', str_repeat('a', 53)],
    'numeric entity at the boundary' => [str_repeat('a', 54) . '&#039;b', str_repeat('a', 54)],
    'full entity before the boundary' => [str_repeat('a', 50) . '&amp;' . str_repeat('b', 5), str_repeat('a', 50) . '&amp;'],
]);

it('escapes the configurable option labels in the order email', function (): void {
    $orderItem = Mage::getModel('sales/order_item')->setProductOptions(['attributes_info' => rawAttributesInfo()]);
    $block = new Mage_Sales_Block_Order_Email_Items_Order_Default();
    $block->setItem($orderItem);

    expect(array_column($block->getItemOptions(), 'value'))->toBe([
        '&lt;img src=x onerror=alert(1)&gt;',
        'Black &amp; &quot;White&quot;',
    ]);
});

it('escapes the additional options that an order keeps', function (string $blockClass): void {
    $orderItem = Mage::getModel('sales/order_item')->setProductOptions(['additional_options' => rawAttributesInfo()]);
    $item = $blockClass === Mage_Sales_Block_Order_Email_Items_Default::class
        ? Mage::getModel('sales/order_invoice_item')->setOrderItem($orderItem)
        : $orderItem;
    $block = new $blockClass();
    $block->setItem($item);

    expect(array_column($block->getItemOptions(), 'value'))->toBe([
        '&lt;img src=x onerror=alert(1)&gt;',
        'Black &amp; &quot;White&quot;',
    ]);
})->with([
    Mage_Sales_Block_Order_Item_Renderer_Default::class,
    Mage_Sales_Block_Order_Email_Items_Default::class,
    Mage_Sales_Block_Order_Email_Items_Order_Default::class,
]);

it('escapes the additional options of a cart item', function (): void {
    $item = new class implements Mage_Catalog_Model_Product_Configuration_Item_Interface {
        #[\Override]
        public function getProduct()
        {
            return Mage::getModel('catalog/product');
        }

        #[\Override]
        public function getOptionByCode($code)
        {
            return $code === 'additional_options'
                ? new Maho\DataObject(['value' => Mage::helper('core')->jsonEncode(rawAttributesInfo())])
                : null;
        }

        #[\Override]
        public function getFileDownloadParams()
        {
            return null;
        }

        #[\Override]
        public function getQty(): ?float
        {
            return 1.0;
        }
    };

    expect(array_column(Mage::helper('catalog/product_configuration')->getCustomOptions($item), 'value'))->toBe([
        '&lt;img src=x onerror=alert(1)&gt;',
        'Black &amp; &quot;White&quot;',
    ]);
});

it('escapes the titles of downloadable links in the item options', function (): void {
    $product = Mage::getModel('catalog/product')
        ->setTypeId('downloadable')
        ->setLinksTitle('Links');
    $item = new class ($product) implements Mage_Catalog_Model_Product_Configuration_Item_Interface {
        public function __construct(private Mage_Catalog_Model_Product $product) {}

        #[\Override]
        public function getProduct()
        {
            return $this->product;
        }

        #[\Override]
        public function getOptionByCode($code)
        {
            return null;
        }

        #[\Override]
        public function getFileDownloadParams()
        {
            return null;
        }

        #[\Override]
        public function getQty(): ?float
        {
            return 1.0;
        }
    };
    $helper = new class extends Mage_Downloadable_Helper_Catalog_Product_Configuration {
        #[\Override]
        public function getLinks(Mage_Catalog_Model_Product_Configuration_Item_Interface $item)
        {
            return [new Maho\DataObject(['title' => '<img src=x onerror=alert(1)>'])];
        }
    };

    $options = $helper->getOptions($item);

    expect($options)->toHaveCount(1)
        ->and($options[0]['label'])->toBe('Links')
        ->and($options[0]['value'])->toBe(['&lt;img src=x onerror=alert(1)&gt;']);
});
