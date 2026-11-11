<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Sales\Api\GraphQL\OrderMutationHandler;
use Mage\Sales\Api\OrderProvider;
use Symfony\Bundle\SecurityBundle\Security;

uses(Tests\MahoBackendTestCase::class);

// One item of 100 and a shipping of 10, invoiced and paid offline, so the order can refund 110.
function refundItemListOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setIncrementId('REFUND-LIST-' . uniqid('', true));
    $order->setStoreId(1);
    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING)->setStatus('processing');
    $order->setBaseCurrencyCode('USD');
    $order->setOrderCurrencyCode('USD');
    $order->setBaseToOrderRate(1.0);
    $order->setSubtotal(100.0)->setBaseSubtotal(100.0);
    $order->setSubtotalInvoiced(100.0)->setBaseSubtotalInvoiced(100.0);
    $order->setShippingAmount(10.0)->setBaseShippingAmount(10.0);
    $order->setShippingInvoiced(10.0)->setBaseShippingInvoiced(10.0);
    $order->setGrandTotal(110.0)->setBaseGrandTotal(110.0);
    $order->setTotalInvoiced(110.0)->setBaseTotalInvoiced(110.0);
    $order->setTotalPaid(110.0)->setBaseTotalPaid(110.0);

    $item = Mage::getModel('sales/order_item');
    $item->setSku('refund-list');
    $item->setProductType('simple');
    $item->setQtyOrdered(1)->setQtyInvoiced(1);
    $item->setPrice(100.0)->setBasePrice(100.0);
    $item->setRowTotal(100.0)->setBaseRowTotal(100.0);
    $item->setRowInvoiced(100.0)->setBaseRowInvoiced(100.0);
    $order->addItem($item);

    $payment = Mage::getModel('sales/order_payment');
    $payment->setMethod('checkmo');
    $payment->setAmountPaid(110.0)->setBaseAmountPaid(110.0);
    $order->setPayment($payment);
    $order->save();

    return Mage::getModel('sales/order')->load($order->getId());
}

function refundItemListReturn(Security $security, Mage_Sales_Model_Order $order, array $variables): array
{
    $handler = new OrderMutationHandler(new OrderProvider($security));

    return $handler->handleCreateCreditMemo(['orderId' => (int) $order->getId()] + $variables, []);
}

function refundItemListItemId(Mage_Sales_Model_Order $order): int
{
    return (int) $order->getAllItems()[0]->getId();
}

describe('A refund with a list of items, in the order service', function (): void {

    it('refunds no item for an empty list', function (): void {
        $order = refundItemListOrder();

        try {
            expect(fn() => Mage::getService('sales/order_document')->refund($order, ['qtys' => []]))
                ->toThrow(Mage_Core_Exception::class, 'Credit memo total must be greater than zero');
            expect((float) Mage::getModel('sales/order')->load($order->getId())->getBaseTotalRefunded())->toBe(0.0);
        } finally {
            $order->delete();
        }
    });

    it('refunds every item and the shipping without a list', function (): void {
        $order = refundItemListOrder();

        try {
            $creditmemo = Mage::getService('sales/order_document')->refund($order, []);
            expect((float) $creditmemo->getBaseGrandTotal())->toBe(110.0);
        } finally {
            $order->delete();
        }
    });

});

describe('The createCreditMemo mutation of the admin GraphQL API', function (): void {

    it('refunds no item, and no shipping, when every qty is 0', function (): void {
        $order = refundItemListOrder();

        try {
            expect(fn() => refundItemListReturn($this->createMock(Security::class), $order, ['items' => [['orderItemId' => refundItemListItemId($order), 'qty' => 0]]]))
                ->toThrow(Mage_Core_Exception::class, 'Credit memo total must be greater than zero');

            refundItemListReturn($this->createMock(Security::class), $order, [
                'items' => [['orderItemId' => refundItemListItemId($order), 'qty' => 0]],
                'adjustmentPositive' => 5,
            ]);
            $reloaded = Mage::getModel('sales/order')->load($order->getId());
            expect((float) $reloaded->getBaseTotalRefunded())->toBe(5.0)
                ->and((float) $reloaded->getBaseShippingRefunded())->toBe(0.0)
                ->and((float) $reloaded->getAllItems()[0]->getQtyRefunded())->toBe(0.0);
        } finally {
            $order->delete();
        }
    });

    it('refunds the shipping that is left, as the admin and REST do', function (): void {
        $order = refundItemListOrder();

        try {
            $result = refundItemListReturn($this->createMock(Security::class), $order, ['items' => [['orderItemId' => refundItemListItemId($order), 'qty' => 1]]]);
            expect($result['createCreditMemo']['creditmemo']['grandTotal'])->toBe(110.0);
        } finally {
            $order->delete();
        }
    });

    it('refunds the shipping amount that the client gives', function (): void {
        $order = refundItemListOrder();

        try {
            $result = refundItemListReturn($this->createMock(Security::class), $order, [
                'items' => [['orderItemId' => refundItemListItemId($order), 'qty' => 1]],
                'shippingAmount' => 0,
            ]);
            expect($result['createCreditMemo']['creditmemo']['grandTotal'])->toBe(100.0);
        } finally {
            $order->delete();
        }
    });

});
