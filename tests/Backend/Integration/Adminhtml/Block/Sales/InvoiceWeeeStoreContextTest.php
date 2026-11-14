<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $this->storeResource = Mage::getResourceSingleton('core/store');
    $this->storeResource->beginTransaction();
    $this->originalStore = Mage::app()->getStore()->getId();
    $this->scopeStores = [];
    foreach (['current', 'invoice'] as $scope) {
        $store = Mage::getModel('core/store')->setCode('invoice_weee_' . $scope)
            ->setName('Invoice WEEE ' . $scope)->setWebsiteId(1)->setGroupId(1)->setIsActive(true)->save();
        $this->scopeStores[$scope] = (int) $store->getId();
        foreach (['tax/weee/enable' => 1, 'tax/sales_display/price' => 3, 'tax/cart_display/price' => 3] as $path => $value) {
            Mage::getModel('core/config')->saveConfig($path, $value, 'stores', $store->getId());
        }
    }
    Mage::getConfig()->reinit();
    Mage::app()->reinitStores();
    Mage::getDesign()->setArea('adminhtml')->setPackageName('default')->setTheme('default');
});

afterEach(function (): void {
    Mage::app()->setCurrentStore($this->originalStore);
    $this->storeResource->rollBack();
});

function documentWeeeApply(Maho\DataObject $item): void
{
    $item->addData([
        'qty' => 1, 'price' => 100, 'base_price' => 100, 'row_total' => 100, 'base_row_total' => 100,
        'tax_amount' => 21, 'base_tax_amount' => 21, 'price_incl_tax' => 120, 'row_total_incl_tax' => 120,
        'weee_tax_applied_amount' => 5, 'base_weee_tax_applied_amount' => 5,
        'weee_tax_applied_row_amount' => 5, 'base_weee_tax_applied_row_amnt' => 5,
    ]);
    Mage::helper('weee')->setApplied($item, [[
        'title' => 'Owned recycling fee', 'amount' => 5, 'base_amount' => 5,
        'row_amount' => 5, 'base_row_amount' => 5,
        'amount_incl_tax' => 6, 'base_amount_incl_tax' => 6,
        'row_amount_incl_tax' => 6, 'base_row_amount_incl_tax' => 6,
    ]]);
}

function invoiceWeeeItem(int $storeId, string $type): Mage_Sales_Model_Order_Invoice_Item
{
    $order = Mage::getModel('sales/order')->setStoreId($storeId)->setBaseCurrencyCode('USD')->setOrderCurrencyCode('USD');
    $orderItem = Mage::getModel('sales/order_item')->setName('Owned invoice item')->setSku('OWNED-INVOICE-ITEM')
        ->setProductType($type)->setProductOptions([]);
    $invoice = Mage::getModel('sales/order_invoice')->setOrder($order);
    $item = Mage::getModel('sales/order_invoice_item')->setInvoice($invoice)->setOrderItem($orderItem);
    documentWeeeApply($item);
    if ($type === 'bundle') {
        $orderItem->setId(1);
        $item->setOrderItemId(1);
        $invoice->addItem($item);
    }
    return $item;
}

function invoiceWeeeHtml(Mage_Sales_Model_Order_Invoice_Item $item): string
{
    Mage::register('current_invoice', $item->getInvoice());
    try {
        $items = Mage::app()->getLayout()->createBlock('adminhtml/sales_order_invoice_view_items');
        $items->addItemRender('default', 'adminhtml/sales_items_renderer_default', 'sales/order/invoice/view/items/renderer/default.phtml');
        $items->addItemRender('downloadable', 'adminhtml/sales_items_renderer_default', 'downloadable/sales/order/invoice/view/items/renderer/downloadable.phtml');
        $items->addItemRender('bundle', 'bundle/adminhtml_sales_order_items_renderer', 'bundle/sales/invoice/view/items/renderer.phtml');
        $items->getItemRenderer($item->getOrderItem()->getProductType())->setOrder($item->getInvoice()->getOrder());
        return $items->getItemHtml($item);
    } finally {
        Mage::unregister('current_invoice');
    }
}

it('renders invoice WEEE details using the invoice store regardless of the current store', function (string $type, int $currentMode, int $invoiceMode): void {
    foreach (['current' => $currentMode, 'invoice' => $invoiceMode] as $scope => $mode) {
        Mage::getModel('core/config')->saveConfig('tax/weee/display_sales', $mode, 'stores', $this->scopeStores[$scope]);
    }
    Mage::getConfig()->reinit();
    Mage::app()->reinitStores();
    $item = invoiceWeeeItem($this->scopeStores['invoice'], $type);
    $before = $item->getData();
    Mage::app()->setCurrentStore($this->scopeStores['current']);
    $actual = invoiceWeeeHtml($item);
    Mage::app()->setCurrentStore($this->scopeStores['invoice']);
    $sameStore = invoiceWeeeHtml($item);

    $document = new DOMDocument();
    $document->loadHTML('<table>' . $sameStore . '</table>');
    $unitInclTax = new DOMXPath($document)->query('(//td)[2]//span[@class="price-incl-tax"]')->item(0)->textContent;
    expect(str_contains($unitInclTax, 'Owned recycling fee'))->toBe($invoiceMode === 2)
        ->and($actual)->toBe($sameStore)
        ->and($item->getData())->toBe($before + ['store_id' => $this->scopeStores['invoice']]);
})->with(['simple', 'configurable', 'downloadable', 'bundle'])->with([
    'different store, separate WEEE' => [0, 2],
    'different store, included WEEE' => [2, 0],
    'same mode, separate WEEE' => [2, 2],
    'same mode, included WEEE' => [0, 0],
]);

function documentWeeeOrder(int $storeId): Mage_Sales_Model_Order
{
    return Mage::getModel('sales/order')->setStoreId($storeId)->setBaseCurrencyCode('USD')->setOrderCurrencyCode('USD');
}

function documentWeeeOrderItem(Mage_Sales_Model_Order $order, string $type): Mage_Sales_Model_Order_Item
{
    return Mage::getModel('sales/order_item')->setId(1)->setName('Owned document item')->setSku('OWNED-DOCUMENT-ITEM')
        ->setProductType($type)->setProductOptions([])->setOrder($order)->setStoreId($order->getStoreId());
}

function documentWeeeRender(string $kind, string $type, int $storeId, int $currentStoreId): string
{
    $order = documentWeeeOrder($storeId);
    $orderItem = documentWeeeOrderItem($order, $type);
    $layout = Mage::app()->getLayout();
    if ($kind === 'creditmemo') {
        $creditmemo = Mage::getModel('sales/order_creditmemo')->setOrder($order);
        $item = Mage::getModel('sales/order_creditmemo_item')->setCreditmemo($creditmemo)->setOrderItem($orderItem)->setOrderItemId(1);
        documentWeeeApply($item);
        $creditmemo->addItem($item);
        $items = $layout->createBlock('adminhtml/sales_order_creditmemo_view_items');
        $items->addItemRender('bundle', 'bundle/adminhtml_sales_order_items_renderer', 'bundle/sales/creditmemo/view/items/renderer.phtml');
        $items->addItemRender('downloadable', 'adminhtml/sales_items_renderer_default', 'downloadable/sales/order/creditmemo/view/items/renderer/downloadable.phtml');
        Mage::register('current_creditmemo', $creditmemo);
        $registryKey = 'current_creditmemo';
    } else {
        $item = $orderItem;
        documentWeeeApply($item);
        $items = $layout->createBlock('adminhtml/sales_order_view_items');
        $items->addItemRender('bundle', 'bundle/adminhtml_sales_order_view_items_renderer', 'bundle/sales/order/view/items/renderer.phtml');
        $items->addItemRender('downloadable', 'adminhtml/sales_order_view_items_renderer_default', 'downloadable/sales/order/view/items/renderer/downloadable.phtml');
        Mage::register('current_order', $order);
        $registryKey = 'current_order';
    }
    Mage::app()->setCurrentStore($currentStoreId);
    try {
        $items->getItemRenderer($type)->setOrder($order);
        return $items->getItemHtml($item);
    } finally {
        Mage::unregister($registryKey);
    }
}

it('renders WEEE details of an order or credit memo item using the store of the document', function (string $kind, string $type, int $currentMode, int $invoiceMode): void {
    foreach (['current' => $currentMode, 'invoice' => $invoiceMode] as $scope => $mode) {
        Mage::getModel('core/config')->saveConfig('tax/weee/display_sales', $mode, 'stores', $this->scopeStores[$scope]);
    }
    Mage::getConfig()->reinit();
    Mage::app()->reinitStores();

    $actual = documentWeeeRender($kind, $type, $this->scopeStores['invoice'], $this->scopeStores['current']);
    $sameStore = documentWeeeRender($kind, $type, $this->scopeStores['invoice'], $this->scopeStores['invoice']);

    expect(str_contains($sameStore, 'Owned recycling fee'))->toBe($invoiceMode === 2)
        ->and($actual)->toBe($sameStore);
})->with(['creditmemo', 'order'])->with(['bundle', 'downloadable'])->with([
    'different store, separate WEEE' => [0, 2],
    'different store, included WEEE' => [2, 0],
]);
