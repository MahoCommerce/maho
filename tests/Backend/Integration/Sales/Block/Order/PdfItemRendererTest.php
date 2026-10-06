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
