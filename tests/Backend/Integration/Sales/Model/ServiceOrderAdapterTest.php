<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A rewrite of the model sales/service_order, as an extension has it: it counts the invoices that it prepares, and
 * it never ships or refunds the item adapter-a.
 */
class ServiceOrderAdapterTestRewrite extends Mage_Sales_Model_Service_Order
{
    /** @var list<string> */
    public static array $calls = [];

    #[\Override]
    public function prepareInvoice($qtys = [])
    {
        self::$calls[] = 'prepareInvoice';
        return parent::prepareInvoice($qtys);
    }

    #[\Override]
    protected function _canShipItem($item, $qtys = [])
    {
        return $item->getSku() !== 'adapter-a' && parent::_canShipItem($item, $qtys);
    }

    #[\Override]
    protected function _canRefundItem($item, $qtys = [], $invoiceQtysRefundLimits = [])
    {
        return $item->getSku() !== 'adapter-a' && parent::_canRefundItem($item, $qtys, $invoiceQtysRefundLimits);
    }
}

// Two simple items: 2 of A at 10 and 3 of B at 20, so the order totals 80.
function serviceOrderAdapterOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setIncrementId('SERVICE-ORDER-ADAPTER-' . uniqid('', true));
    $order->setStoreId(1);
    $order->setState(Mage_Sales_Model_Order::STATE_NEW);
    $order->setBaseCurrencyCode('USD');
    $order->setOrderCurrencyCode('USD');
    $order->setBaseToOrderRate(1.0);
    $order->setSubtotal(80.0)->setBaseSubtotal(80.0);
    $order->setGrandTotal(80.0)->setBaseGrandTotal(80.0);

    foreach (['adapter-a' => [2, 10.0], 'adapter-b' => [3, 20.0]] as $sku => [$qty, $price]) {
        $item = Mage::getModel('sales/order_item');
        $item->setSku($sku);
        $item->setProductType('simple');
        $item->setQtyOrdered($qty);
        $item->setPrice($price)->setBasePrice($price);
        $item->setRowTotal($qty * $price)->setBaseRowTotal($qty * $price);
        $order->addItem($item);
    }

    $payment = Mage::getModel('sales/order_payment');
    $payment->setMethod('checkmo');
    $order->setPayment($payment);
    $order->save();

    return $order;
}

function serviceOrderAdapterItemId(Mage_Sales_Model_Order $order, string $sku): int
{
    foreach ($order->getAllItems() as $item) {
        if ($item->getSku() === $sku) {
            return (int) $item->getId();
        }
    }
    throw new RuntimeException("No order item $sku");
}

function serviceOrderAdapterInvoiceAll(Mage_Sales_Model_Order $order): Mage_Sales_Model_Order_Invoice
{
    $invoice = Mage::getService('sales/order_document')->buildInvoice($order);
    $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE)->register();
    Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder())->save();
    return $invoice;
}

/**
 * @return array{total_qty: float, base_grand_total: float, qtys: array<string, float>}
 */
function serviceOrderAdapterSummary(Mage_Sales_Model_Abstract $document): array
{
    $qtys = [];
    foreach ($document->getAllItems() as $item) {
        $qtys[$item->getOrderItem()->getSku()] = (float) $item->getQty();
    }
    return [
        'total_qty' => (float) $document->getTotalQty(),
        'base_grand_total' => round((float) $document->getBaseGrandTotal(), 4),
        'qtys' => $qtys,
    ];
}

describe('The deprecated Mage_Sales_Model_Service_Order', function () {
    test('prepares the same invoice as the order service', function () {
        $order = serviceOrderAdapterOrder();
        $qtys = [serviceOrderAdapterItemId($order, 'adapter-a') => '1,0'];

        $adapter = serviceOrderAdapterSummary(Mage::getModel('sales/service_order', $order)->prepareInvoice($qtys));
        $service = serviceOrderAdapterSummary(Mage::getService('sales/order_document')->buildInvoice($order, $qtys));

        expect($adapter)->toBe($service)
            ->and($service)->toBe(['total_qty' => 1.0, 'base_grand_total' => 10.0, 'qtys' => ['adapter-a' => 1.0, 'adapter-b' => 0.0]]);
    });

    test('prepares the same shipment as the order service', function () {
        $order = serviceOrderAdapterOrder();
        $qtys = [serviceOrderAdapterItemId($order, 'adapter-b') => 2];

        $adapter = serviceOrderAdapterSummary(Mage::getModel('sales/service_order', $order)->prepareShipment($qtys));
        $service = serviceOrderAdapterSummary(Mage::getService('sales/order_document')->buildShipment($order, $qtys));

        expect($adapter)->toBe($service)
            ->and($service['qtys'])->toBe(['adapter-b' => 2.0]);
    });

    test('prepares the same credit memo of the order as the order service', function () {
        $order = serviceOrderAdapterOrder();
        serviceOrderAdapterInvoiceAll($order);
        $order = Mage::getModel('sales/order')->load($order->getId());
        $data = ['qtys' => [serviceOrderAdapterItemId($order, 'adapter-b') => 1], 'adjustment_positive' => '2,5'];

        $adapter = serviceOrderAdapterSummary(Mage::getModel('sales/service_order', $order)->prepareCreditmemo($data));
        $service = serviceOrderAdapterSummary(Mage::getService('sales/order_document')->buildCreditmemo($order, $data));

        expect($adapter)->toBe($service)
            ->and($service)->toBe(['total_qty' => 1.0, 'base_grand_total' => 22.5, 'qtys' => ['adapter-b' => 1.0]]);
    });

    test('prepares the same credit memo of an invoice as the order service', function () {
        $order = serviceOrderAdapterOrder();
        $invoice = serviceOrderAdapterInvoiceAll($order);

        $adapter = serviceOrderAdapterSummary(Mage::getModel('sales/service_order', $order)->prepareInvoiceCreditmemo($invoice));
        $service = serviceOrderAdapterSummary(Mage::getService('sales/order_document')->buildCreditmemo($order, [], $invoice));

        expect($adapter)->toBe($service)
            ->and($service)->toBe(['total_qty' => 5.0, 'base_grand_total' => 80.0, 'qtys' => ['adapter-a' => 2.0, 'adapter-b' => 3.0]]);
    });

    test('builds with the convertor that setConvertor() gives', function () {
        $convertor = new class extends Mage_Sales_Model_Convert_Order {
            public int $invoices = 0;

            #[\Override]
            public function toInvoice(Mage_Sales_Model_Order $order)
            {
                $this->invoices++;
                return parent::toInvoice($order);
            }
        };

        Mage::getModel('sales/service_order', serviceOrderAdapterOrder())->setConvertor($convertor)->prepareInvoice();

        expect($convertor->invoices)->toBe(1);
    });

    test('core builds each document through a rewrite of the model', function () {
        $order = serviceOrderAdapterOrder();
        serviceOrderAdapterInvoiceAll($order);
        $order = Mage::getModel('sales/order')->load($order->getId());
        $cache = new ReflectionProperty(Mage_Core_Model_Config::class, '_classNameCache');
        $cache->setValue(Mage::getConfig(), []);
        Mage::getConfig()->setNode('global/models/sales/rewrite/service_order', ServiceOrderAdapterTestRewrite::class);
        ServiceOrderAdapterTestRewrite::$calls = [];

        try {
            $service = Mage::getService('sales/order_document');
            $order->prepareInvoice();
            $shipment = serviceOrderAdapterSummary($service->buildShipment($order));
            $creditmemo = serviceOrderAdapterSummary($service->buildCreditmemo($order));
        } finally {
            Mage::getConfig()->setNode('global/models/sales/rewrite/service_order', '');
            $cache->setValue(Mage::getConfig(), []);
        }

        expect(ServiceOrderAdapterTestRewrite::$calls)->toBe(['prepareInvoice'])
            ->and($shipment['qtys'])->toBe(['adapter-b' => 3.0])
            ->and($creditmemo['qtys'])->toBe(['adapter-b' => 3.0])
            ->and(serviceOrderAdapterSummary(Mage::getService('sales/order_document')->buildShipment($order))['qtys'])
            ->toBe(['adapter-a' => 2.0, 'adapter-b' => 3.0]);
    });
});
