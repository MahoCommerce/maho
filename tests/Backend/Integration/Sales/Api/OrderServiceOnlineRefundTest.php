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

// One item of 100, invoiced and paid offline, so the order can refund 100.
function onlineRefundPaidOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setIncrementId('ONLINE-REFUND-' . uniqid('', true));
    $order->setStoreId(1);
    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING)->setStatus('processing');
    $order->setBaseCurrencyCode('USD');
    $order->setOrderCurrencyCode('USD');
    $order->setBaseToOrderRate(1.0);
    $order->setSubtotal(100.0)->setBaseSubtotal(100.0);
    $order->setSubtotalInvoiced(100.0)->setBaseSubtotalInvoiced(100.0);
    $order->setGrandTotal(100.0)->setBaseGrandTotal(100.0);
    $order->setTotalInvoiced(100.0)->setBaseTotalInvoiced(100.0);
    $order->setTotalPaid(100.0)->setBaseTotalPaid(100.0);

    $item = Mage::getModel('sales/order_item');
    $item->setSku('online-refund');
    $item->setProductType('simple');
    $item->setQtyOrdered(1)->setQtyInvoiced(1);
    $item->setPrice(100.0)->setBasePrice(100.0);
    $item->setRowTotal(100.0)->setBaseRowTotal(100.0);
    $item->setRowInvoiced(100.0)->setBaseRowInvoiced(100.0);
    $order->addItem($item);

    $payment = Mage::getModel('sales/order_payment');
    $payment->setMethod('checkmo');
    $payment->setAmountPaid(100.0)->setBaseAmountPaid(100.0);
    $order->setPayment($payment);
    $order->save();

    return Mage::getModel('sales/order')->load($order->getId());
}

describe('The order service refund of a credit memo without an invoice', function (): void {

    it('refuses an online refund, which would reach no payment gateway', function (): void {
        $order = onlineRefundPaidOrder();

        try {
            expect(fn() => \Mage::getService('sales/order')->refund($order, ['qtys' => []], null, false))
                ->toThrow(Mage_Core_Exception::class, 'Cannot refund online');

            $reloaded = Mage::getModel('sales/order')->load($order->getId());
            expect((float) $reloaded->getBaseTotalRefunded())->toBe(0.0)
                ->and((float) $reloaded->getBaseTotalOnlineRefunded())->toBe(0.0)
                ->and($reloaded->getCreditmemosCollection()->getSize())->toBe(0);
        } finally {
            $order->delete();
        }
    });

    it('refunds the same credit memo offline', function (): void {
        $order = onlineRefundPaidOrder();

        try {
            $creditmemo = \Mage::getService('sales/order')->refund($order, ['qtys' => []], null, true);

            $reloaded = Mage::getModel('sales/order')->load($order->getId());
            expect((float) $creditmemo->getBaseGrandTotal())->toBe(100.0)
                ->and((float) $reloaded->getBaseTotalRefunded())->toBe(100.0)
                ->and((float) $reloaded->getBaseTotalOfflineRefunded())->toBe(100.0)
                ->and((float) $reloaded->getBaseTotalOnlineRefunded())->toBe(0.0);
        } finally {
            $order->delete();
        }
    });

    it('refunds an admin GraphQL processReturn offline', function (): void {
        $order = onlineRefundPaidOrder();
        $orderItemId = (int) $order->getAllItems()[0]->getId();

        try {
            $handler = new OrderMutationHandler(new OrderProvider($this->createMock(Security::class)));
            $result = $handler->handleProcessReturn([
                'orderId' => (int) $order->getId(),
                'items' => [['orderItemId' => $orderItemId, 'qty' => 1]],
            ], []);

            $reloaded = Mage::getModel('sales/order')->load($order->getId());
            expect($result['processReturn']['success'])->toBeTrue()
                ->and($result['processReturn']['creditmemo']['grandTotal'])->toBe(100.0)
                ->and((float) $reloaded->getBaseTotalRefunded())->toBe(100.0)
                ->and((float) $reloaded->getBaseTotalOfflineRefunded())->toBe(100.0)
                ->and((float) $reloaded->getBaseTotalOnlineRefunded())->toBe(0.0);
        } finally {
            $order->delete();
        }
    });

});
