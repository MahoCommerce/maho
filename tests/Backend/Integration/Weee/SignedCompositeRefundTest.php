<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('refunds signed item tax on the priced lines of a composite product', function (string $type, bool $childPrices, bool $sharedChild = false): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => 0.8]]);
        $store = setStoreDisplayCurrency('EUR', 'USD,EUR');
        $baseSubtotal = $childPrices ? 10.0 : 4.0;
        $subtotal = $baseSubtotal * 0.8;
        $order = Mage::getModel('sales/order')->setStoreId((int) $store->getId())
            ->setIncrementId('signed-composite-' . uniqid())->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
            ->setBaseCurrencyCode('USD')->setOrderCurrencyCode('EUR')->setBaseToOrderRate(0.8)
            ->setSubtotal($subtotal)->setBaseSubtotal($baseSubtotal)->setTaxAmount(-0.04)->setBaseTaxAmount(-0.07)
            ->setGrandTotal($subtotal - 0.04)->setBaseGrandTotal($baseSubtotal - 0.07);
        $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));
        $addItem = static function (string $type, float $qty, float $basePrice) use ($order): Mage_Sales_Model_Order_Item {
            $item = Mage::getModel('sales/order_item')->setProductType($type)->setSku(uniqid('line-'))->setQtyOrdered($qty)
                ->setPrice($basePrice * 0.8)->setBasePrice($basePrice)->setRowTotal($qty * $basePrice * 0.8)->setBaseRowTotal($qty * $basePrice);
            $order->addItem($item);
            return $item;
        };
        $simple = $addItem('simple', 1.0, 1.0);
        $parent = $addItem($type, 3.0, $childPrices ? 0.0 : 1.0);
        $parent->setProductOptions(['product_calculations' => $childPrices
            ? Mage_Catalog_Model_Product_Type_Abstract::CALCULATE_CHILD
            : Mage_Catalog_Model_Product_Type_Abstract::CALCULATE_PARENT]);
        $children = [];
        foreach ($type === 'bundle' ? [6.0, 3.0] : [3.0] as $qty) {
            $children[] = $addItem('simple', $qty, $childPrices ? 1.0 : 0.0)->setParentItem($parent);
        }
        $order->save();
        $orderId = (int) $order->getId();
        $first = [$simple->getId() => 1];
        $second = [$parent->getId() => 3];
        foreach ($children as $child) {
            $second[$child->getId()] = $child->getQtyOrdered();
        }
        if ($sharedChild) {
            $id = end($children)->getId();
            $first[$id] = 1;
            $second[$id]--;
        }
        $documents = Mage::getService('sales/order_document');
        $invoices = [];
        foreach ([$first, $second] as $qtys) {
            $order = Mage::getModel('sales/order')->load($orderId);
            $invoice = $documents->invoice($order, $qtys, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE, sendEmail: false);
            $invoices[] = Mage::getModel('sales/order_invoice')->load($invoice->getId());
        }
        $qtys = [];
        foreach ($invoices[1]->getAllItems() as $item) {
            if (!$item->getOrderItem()->isDummy()) {
                $qtys[$item->getOrderItemId()] = $item->getQty();
            }
        }
        expect($qtys)->toHaveCount($childPrices ? 2 : 1);
        $order = Mage::getModel('sales/order')->load($orderId);
        $memo = $documents->refund($order, ['qtys' => $qtys], sendEmail: false);
        $memo = Mage::getModel('sales/order_creditmemo')->load($memo->getId());
        expect($memo->getInvoiceId())->toBeNull()
            ->and($memo->getBaseGrandTotal())->toBe(round($invoices[1]->getBaseGrandTotal() - ($sharedChild ? 0.07 : 0.0), 2))
            ->and($memo->getGrandTotal())->toBe(round($invoices[1]->getGrandTotal() - ($sharedChild ? 0.04 : 0.0), 2));
        $order = Mage::getModel('sales/order')->load($orderId);
        $documents->refund($order, ['qtys' => $first], invoice: $invoices[0], sendEmail: false);
        $order = Mage::getModel('sales/order')->load($orderId);
        expect($order->getTotalRefunded())->toBe($order->getTotalPaid())
            ->and($order->getBaseTotalRefunded())->toBe($order->getBaseTotalPaid());
    } finally {
        $connection->rollBack();
        resetCurrencyState();
    }
})->with([
    'configurable with a priced parent' => ['configurable', false],
    'fixed-price bundle' => ['bundle', false],
    'dynamic-price bundle' => ['bundle', true],
    'dynamic-price bundle with its last selection on both invoices' => ['bundle', true, true],
]);
