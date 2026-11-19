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

it('escapes the configurable option labels in the order email', function (): void {
    $orderItem = Mage::getModel('sales/order_item')->setProductOptions(['attributes_info' => rawAttributesInfo()]);
    $block = new Mage_Sales_Block_Order_Email_Items_Order_Default();
    $block->setItem($orderItem);

    expect(array_column($block->getItemOptions(), 'value'))->toBe([
        '&lt;img src=x onerror=alert(1)&gt;',
        'Black &amp; &quot;White&quot;',
    ]);
});
