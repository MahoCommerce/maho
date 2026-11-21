<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function pdfItemRenderer(Mage_Sales_Block_Order_Pdf_Abstract $block, string $type): ?Mage_Sales_Model_Order_Pdf_Items_Abstract
{
    return (new ReflectionMethod($block, '_getItemRenderer'))->invoke($block, $type);
}

it('uses the item renderer that global/pdf declares for the product type', function (string $block, string $type, string $class) {
    expect(pdfItemRenderer(new $block(), $type))->toBeInstanceOf($class);
})->with([
    [Mage_Sales_Block_Order_Pdf_Invoice::class, 'default', Mage_Sales_Model_Order_Pdf_Items_Invoice_Default::class],
    [Mage_Sales_Block_Order_Pdf_Invoice::class, 'grouped', Mage_Sales_Model_Order_Pdf_Items_Invoice_Grouped::class],
    [Mage_Sales_Block_Order_Pdf_Invoice::class, 'bundle', Mage_Bundle_Model_Sales_Order_Pdf_Items_Invoice::class],
    [Mage_Sales_Block_Order_Pdf_Invoice::class, 'downloadable', Mage_Downloadable_Model_Sales_Order_Pdf_Items_Invoice::class],
    [Mage_Sales_Block_Order_Pdf_Creditmemo::class, 'default', Mage_Sales_Model_Order_Pdf_Items_Creditmemo_Default::class],
    [Mage_Sales_Block_Order_Pdf_Creditmemo::class, 'grouped', Mage_Sales_Model_Order_Pdf_Items_Creditmemo_Grouped::class],
    [Mage_Sales_Block_Order_Pdf_Creditmemo::class, 'bundle', Mage_Bundle_Model_Sales_Order_Pdf_Items_Creditmemo::class],
    [Mage_Sales_Block_Order_Pdf_Creditmemo::class, 'downloadable', Mage_Downloadable_Model_Sales_Order_Pdf_Items_Creditmemo::class],
    [Mage_Sales_Block_Order_Pdf_Shipment::class, 'default', Mage_Sales_Model_Order_Pdf_Items_Shipment_Default::class],
    [Mage_Sales_Block_Order_Pdf_Shipment::class, 'bundle', Mage_Bundle_Model_Sales_Order_Pdf_Items_Shipment::class],
]);

it('returns no item renderer for a product type that global/pdf does not declare', function (string $block) {
    expect(pdfItemRenderer(new $block(), 'simple'))->toBeNull();
})->with([
    Mage_Sales_Block_Order_Pdf_Invoice::class,
    Mage_Sales_Block_Order_Pdf_Creditmemo::class,
    Mage_Sales_Block_Order_Pdf_Shipment::class,
]);

it('prints the item cells in the column order of the document header, with the item SKU', function (string $block, string $type, array $columns) {
    $section = strtolower(substr($block, strrpos($block, '_') + 1));
    $order = Mage::getModel('sales/order')->setOrderCurrencyCode('USD')->setBaseCurrencyCode('USD');
    $orderItem = Mage::getModel('sales/order_item')->setProductType($type)->setProductOptions([]);
    $document = Mage::getModel("sales/order_{$section}")->setOrder($order);
    $item = Mage::getModel("sales/order_{$section}_item")
        ->setData(['sku' => 'PDF-ITEM-SKU', 'name' => 'PDF Item', 'qty' => 1])
        ->setOrderItem($orderItem);
    $item->{'set' . ucfirst($section)}($document);

    $renderer = pdfItemRenderer(new $block(), $type);
    $renderer->setItem($item)->setOrder($order)->setSource($document);

    $restoreDesign = (new ReflectionMethod(Mage_Sales_Model_Order_Pdf_Abstract::class, 'useAdminDesign'))
        ->invoke(Mage::getModel("sales/order_pdf_{$section}"));
    try {
        $html = $renderer->toHtml();
    } finally {
        $restoreDesign();
    }

    preg_match_all('/<td class="col-([a-z]+)/', $html, $cells);
    expect($cells[1])->toBe($columns)
        ->and($html)->toContain('PDF-ITEM-SKU');
})->with(function () {
    $invoice = ['products', 'sku', 'price', 'qty', 'tax', 'subtotal'];
    $creditmemo = ['products', 'sku', 'price', 'discount', 'qty', 'tax', 'subtotal'];
    $shipment = ['qty', 'products', 'sku'];
    return [
        [Mage_Sales_Block_Order_Pdf_Invoice::class, 'default', $invoice],
        [Mage_Sales_Block_Order_Pdf_Invoice::class, 'grouped', $invoice],
        [Mage_Sales_Block_Order_Pdf_Invoice::class, 'bundle', $invoice],
        [Mage_Sales_Block_Order_Pdf_Invoice::class, 'downloadable', $invoice],
        [Mage_Sales_Block_Order_Pdf_Creditmemo::class, 'default', $creditmemo],
        [Mage_Sales_Block_Order_Pdf_Creditmemo::class, 'grouped', $creditmemo],
        [Mage_Sales_Block_Order_Pdf_Creditmemo::class, 'bundle', $creditmemo],
        [Mage_Sales_Block_Order_Pdf_Creditmemo::class, 'downloadable', $creditmemo],
        [Mage_Sales_Block_Order_Pdf_Shipment::class, 'default', $shipment],
        [Mage_Sales_Block_Order_Pdf_Shipment::class, 'bundle', $shipment],
    ];
});
