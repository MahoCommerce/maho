<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use Mage\Sales\Api\CreditMemoProcessor;
use Mage\Sales\Api\InvoiceProcessor;
use Mage\Sales\Api\OrderProcessor;
use Mage\Sales\Api\ShipmentProcessor;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin pages and the API cancel, hold, invoice, ship and refund an order, and change the tracking numbers and
 * the comments of a shipment, through Mage_Sales_Service_Order and Mage_Sales_Service_Order_Document (#1348). Each
 * test gives the same input to the transports and compares the outcome.
 */

const SALES_PARITY_REGISTRY_KEYS = ['sales_order', 'current_order', 'current_invoice', 'current_shipment', 'current_creditmemo'];

/**
 * @param array<string, mixed> $data
 */
function salesParityOrder(array $data = []): Mage_Sales_Model_Order
{
    $uniqueId = strtoupper(bin2hex(random_bytes(6)));
    $order = Mage::getModel('sales/order')->setStoreId(1)
        ->setData('state', Mage_Sales_Model_Order::STATE_NEW)
        ->setStatus('pending')
        ->setIncrementId('PARITY' . $uniqueId)
        ->setCustomerIsGuest()
        ->setCustomerEmail("sales.parity.{$uniqueId}@example.com")
        ->setBaseCurrencyCode('USD')->setOrderCurrencyCode('USD')->setGlobalCurrencyCode('USD')->setStoreCurrencyCode('USD')
        ->setBaseToGlobalRate(1)->setBaseToOrderRate(1)
        ->setSubtotal(10)->setBaseSubtotal(10)->setGrandTotal(10)->setBaseGrandTotal(10);
    $order->addItem(Mage::getModel('sales/order_item')->setData([
        'product_type' => 'simple',
        'sku' => 'parity-' . strtolower($uniqueId),
        'name' => 'Parity item',
        'qty_ordered' => 2,
        'price' => 5, 'base_price' => 5, 'row_total' => 10, 'base_row_total' => 10,
        'is_virtual' => 0,
    ]));
    $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));
    $order->addData($data)->save();

    return Mage::getModel('sales/order')->load($order->getId());
}

function salesParityItemId(Mage_Sales_Model_Order $order): int
{
    return (int) $order->getItemsCollection()->getFirstItem()->getId();
}

/**
 * Call the processor $processorClass with the operation $operationName, as the API kernel does.
 *
 * @param class-string $processorClass
 * @param array<string, mixed> $uriVariables
 * @param array<string, mixed> $body
 */
function salesParityApi(string $processorClass, string $operationName, array $uriVariables, array $body = [], string $method = 'POST'): array
{
    $request = AdminApiParity::jsonRequest($method, '/api/rest/v2/sales', $body);
    $operation = $method === 'DELETE' ? new Delete(name: $operationName) : new Post(name: $operationName);

    return AdminApiParity::api($request, fn() => new $processorClass(AdminApiParity::adminSecurity())
        ->process(null, $operation, $uriVariables, ['request' => $request]));
}

/**
 * Post $fields to an admin action that answers with JSON on an error, and return the decoded answer.
 *
 * @param class-string<Mage_Adminhtml_Controller_Action> $controllerClass
 * @param array<string, mixed> $fields
 * @return array<string, mixed>
 */
function salesParityAdminJson(string $controllerClass, string $controller, string $action, array $fields): array
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create("http://localhost/admin/{$controller}/{$action}", 'POST', $fields));
    $request->setRouteName('adminhtml')->setControllerName($controller)->setActionName($action)->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();

    new $controllerClass($request, $response)->{$action . 'Action'}();

    return (array) json_decode((string) $response->getBody(), true);
}

/**
 * Ship one item of $order twice, the first shipment with a tracking number.
 *
 * @return array{Mage_Sales_Model_Order_Shipment, Mage_Sales_Model_Order_Shipment}
 */
function salesParityShipTwice(Mage_Sales_Model_Order $order): array
{
    $service = Mage::getService('sales/order_document');
    $itemId = salesParityItemId($order);
    $track = Mage::getModel('sales/order_shipment_track')->setNumber('PARITY-1')->setCarrierCode('custom')->setTitle('Custom');

    return [$service->ship($order, [$itemId => 1], [$track]), $service->ship($order, [$itemId => 1])];
}

/**
 * An order as salesParityOrder() makes it, which is invoiced and paid offline, so that it can refund 10.
 */
function salesParityPaidOrder(): Mage_Sales_Model_Order
{
    $order = salesParityOrder([
        'state' => Mage_Sales_Model_Order::STATE_PROCESSING, 'status' => 'processing',
        'subtotal_invoiced' => 10, 'base_subtotal_invoiced' => 10,
        'total_invoiced' => 10, 'base_total_invoiced' => 10,
        'total_paid' => 10, 'base_total_paid' => 10,
        'store_to_base_rate' => 1, 'store_to_order_rate' => 1,
    ]);
    $order->getItemsCollection()->getFirstItem()
        ->setQtyInvoiced(2)->setRowInvoiced(10)->setBaseRowInvoiced(10)
        ->save();

    return Mage::getModel('sales/order')->load($order->getId());
}

/**
 * Return the stored rows of the order $orderId and of its documents that a transport must write the same way.
 *
 * @return array<string, mixed>
 */
function salesParityStoredRows(int $orderId): array
{
    $order = Mage::getModel('sales/order')->load($orderId);
    $rows = static function (iterable $documents, array $fields): array {
        $result = [];
        foreach ($documents as $document) {
            $row = [];
            foreach ($fields as $field) {
                $value = $document->getData($field);
                $row[$field] = is_numeric($value) ? (float) $value : $value;
            }
            $result[] = $row;
        }
        return $result;
    };

    return [
        'order' => $rows([$order], [
            'state', 'status', 'total_paid', 'total_invoiced', 'total_refunded', 'total_offline_refunded', 'total_canceled',
            'hold_before_state', 'hold_before_status',
        ])[0],
        'items' => $rows($order->getItemsCollection(), ['qty_invoiced', 'qty_shipped', 'qty_refunded', 'qty_canceled']),
        'invoices' => $rows($order->getInvoiceCollection(), ['state', 'grand_total', 'total_qty', 'email_sent']),
        'shipments' => $rows($order->getShipmentsCollection(), ['total_qty', 'shipment_status', 'email_sent']),
        'creditmemos' => $rows($order->getCreditmemosCollection(), ['state', 'grand_total', 'offline_requested', 'email_sent']),
        'comments' => array_map(
            static fn(Mage_Sales_Model_Order_Status_History $history): array => [(string) $history->getComment(), (bool) $history->getIsCustomerNotified()],
            array_values($order->getStatusHistoryCollection(true)->getItems()),
        ),
    ];
}

beforeEach(function (): void {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    array_map(Mage::unregister(...), SALES_PARITY_REGISTRY_KEYS);
    $this->orders = [];
});

afterEach(function (): void {
    array_map(Mage::unregister(...), SALES_PARITY_REGISTRY_KEYS);
    Mage::register('isSecureArea', true, true);
    foreach ($this->orders as $order) {
        Mage::getModel('sales/order')->load($order->getId())->delete();
    }
    Mage::unregister('isSecureArea');
});

describe('order actions', function (): void {
    it('refuses to cancel a canceled order on both transports', function (): void {
        $this->orders[] = $order = salesParityOrder(['state' => Mage_Sales_Model_Order::STATE_CANCELED, 'status' => 'canceled']);

        $admin = AdminApiParity::admin(Mage_Adminhtml_Sales_OrderController::class, 'cancel', ['order_id' => $order->getId()]);
        $api = salesParityApi(OrderProcessor::class, 'order_cancel', ['id' => $order->getId()]);

        expect($admin)->toBe(['success' => [], 'errors' => ['Order cannot be cancelled']])
            ->and($api)->toMatchArray(['status' => 409, 'errors' => ['Order cannot be cancelled']]);
    });

    it('refuses to hold an order that is on hold on both transports', function (): void {
        $this->orders[] = $order = salesParityOrder(['state' => Mage_Sales_Model_Order::STATE_HOLDED, 'status' => 'holded']);

        $admin = AdminApiParity::admin(Mage_Adminhtml_Sales_OrderController::class, 'hold', ['order_id' => $order->getId()]);
        $api = salesParityApi(OrderProcessor::class, 'order_hold', ['id' => $order->getId()]);

        expect($admin['errors'])->toBe(['Order cannot be held'])
            ->and($api)->toMatchArray(['status' => 409, 'errors' => ['Order cannot be held']]);
    });
});

describe('invoices', function (): void {
    it('refuses to invoice a canceled order on both transports', function (): void {
        $this->orders[] = $order = salesParityOrder(['state' => Mage_Sales_Model_Order::STATE_CANCELED, 'status' => 'canceled']);
        $message = 'Order cannot be invoiced (already fully invoiced or not in an invoiceable state)';

        $admin = AdminApiParity::admin(Mage_Adminhtml_Sales_Order_InvoiceController::class, 'save', ['order_id' => $order->getId()]);
        $api = salesParityApi(InvoiceProcessor::class, 'order_invoice_create', ['orderId' => $order->getId()]);

        expect($admin['errors'])->toBe([$message])
            ->and($api)->toMatchArray(['status' => 409, 'errors' => [$message]]);
    });

    it('refuses a qty above the qty to invoice on both transports', function (): void {
        $this->orders[] = $order = salesParityOrder();
        $itemId = salesParityItemId($order);
        $message = "Qty to invoice for order item {$itemId} exceeds the qty available to invoice";

        $admin = AdminApiParity::admin(Mage_Adminhtml_Sales_Order_InvoiceController::class, 'save', [
            'order_id' => $order->getId(),
            'invoice' => ['items' => [$itemId => 3]],
        ]);
        $api = salesParityApi(InvoiceProcessor::class, 'order_invoice_create', ['orderId' => $order->getId()], [
            'items' => [['orderItemId' => $itemId, 'qty' => 3]],
        ]);

        expect($admin['errors'])->toBe([$message])
            ->and($api)->toMatchArray(['status' => 422, 'errors' => [$message]])
            ->and(Mage::getModel('sales/order')->load($order->getId())->getInvoiceCollection()->count())->toBe(0);
    });
});

describe('shipments', function (): void {
    it('refuses a shipment apart from the invoice when the order forces both on both transports', function (): void {
        $this->orders[] = $order = salesParityOrder(['forced_do_shipment_with_invoice' => 1]);
        $message = 'Cannot do shipment for the order separately from invoice.';

        $admin = AdminApiParity::admin(Mage_Adminhtml_Sales_Order_ShipmentController::class, 'save', ['order_id' => $order->getId()]);
        $api = salesParityApi(ShipmentProcessor::class, 'order_shipment_create', ['orderId' => $order->getId()], ['notifyCustomer' => false]);

        expect($admin['errors'])->toBe([$message])
            ->and($api)->toMatchArray(['status' => 409, 'errors' => [$message]]);
    });

    it('refuses an empty tracking number on both transports', function (): void {
        $this->orders[] = $order = salesParityOrder();
        [$shipment] = salesParityShipTwice($order);

        $admin = salesParityAdminJson(Mage_Adminhtml_Sales_Order_ShipmentController::class, 'sales_order_shipment', 'addTrack', [
            'shipment_id' => $shipment->getId(), 'carrier' => 'custom', 'title' => 'Custom', 'number' => ' ',
        ]);
        $api = salesParityApi(ShipmentProcessor::class, 'add_shipment_track', ['id' => $shipment->getId()], ['trackNumber' => ' ']);

        expect($admin)->toBe(['error' => true, 'message' => 'Track number is required'])
            ->and($api)->toMatchArray(['status' => 422, 'errors' => ['Track number is required'], 'fields' => ['trackNumber']])
            ->and(Mage::getModel('sales/order_shipment')->load($shipment->getId())->getAllTracks())->toHaveCount(1);
    });

    it('refuses to remove the tracking number of another shipment on both transports', function (): void {
        $this->orders[] = $order = salesParityOrder();
        [$tracked, $other] = salesParityShipTwice($order);
        $trackId = (int) Mage::getModel('sales/order_shipment')->load($tracked->getId())->getTracksCollection()->getFirstItem()->getId();
        $message = 'Tracking entry not found for this shipment';

        $admin = salesParityAdminJson(Mage_Adminhtml_Sales_Order_ShipmentController::class, 'sales_order_shipment', 'removeTrack', [
            'shipment_id' => $other->getId(), 'track_id' => $trackId,
        ]);
        $api = salesParityApi(ShipmentProcessor::class, 'remove_shipment_track', ['id' => $other->getId(), 'trackId' => $trackId], [], 'DELETE');

        expect($admin)->toBe(['error' => true, 'message' => $message])
            ->and($api)->toMatchArray(['status' => 404, 'errors' => [$message]])
            ->and(Mage::getModel('sales/order_shipment_track')->load($trackId)->getId())->not->toBeNull();
    });

    it('refuses an empty shipment comment on both transports', function (): void {
        $this->orders[] = $order = salesParityOrder();
        [$shipment] = salesParityShipTwice($order);

        $admin = salesParityAdminJson(Mage_Adminhtml_Sales_Order_ShipmentController::class, 'sales_order_shipment', 'addComment', [
            'id' => $shipment->getId(), 'comment' => ['comment' => '  '],
        ]);
        $api = salesParityApi(ShipmentProcessor::class, 'shipment_add_comment', ['id' => $shipment->getId()], ['comment' => '  ']);

        expect($admin)->toBe(['error' => true, 'message' => 'Comment text is required'])
            ->and($api)->toMatchArray(['status' => 422, 'errors' => ['Comment text is required']]);
    });
});

describe('credit memos', function (): void {
    it('refuses to refund an order that has no payment on both transports', function (): void {
        $this->orders[] = $order = salesParityOrder();
        $message = 'Order cannot be refunded (already fully refunded or not in a refundable state)';

        $admin = AdminApiParity::admin(Mage_Adminhtml_Sales_Order_CreditmemoController::class, 'save', ['order_id' => $order->getId()]);
        $api = salesParityApi(CreditMemoProcessor::class, 'order_creditmemo_create', ['orderId' => $order->getId()]);

        expect($admin['errors'])->toBe([$message])
            ->and($api)->toMatchArray(['status' => 409, 'errors' => [$message]]);
    });

    it('refuses a refund above the refundable balance on both transports', function (): void {
        $this->orders[] = $order = salesParityOrder([
            'total_paid' => 10, 'base_total_paid' => 10, 'total_invoiced' => 10, 'base_total_invoiced' => 10,
            'store_to_base_rate' => 1, 'store_to_order_rate' => 1,
        ]);
        $order->getItemsCollection()->getFirstItem()->setQtyInvoiced(2)->save();
        $message = "Refund amount exceeds the order's refundable balance";

        $admin = AdminApiParity::admin(Mage_Adminhtml_Sales_Order_CreditmemoController::class, 'save', [
            'order_id' => $order->getId(),
            'creditmemo' => ['adjustment_positive' => 50, 'do_offline' => 1],
        ]);
        $api = salesParityApi(CreditMemoProcessor::class, 'order_creditmemo_create', ['orderId' => $order->getId()], ['adjustmentPositive' => 50]);

        expect($admin['errors'])->toBe([$message])
            ->and($api)->toMatchArray(['status' => 422, 'errors' => [$message]])
            ->and(Mage::getModel('sales/order')->load($order->getId())->getCreditmemosCollection()->getSize())->toBe(0);
    });
});

describe('order items', function (): void {
    it('refuses an item of another order on both transports', function (string $document): void {
        $this->orders[] = $order = salesParityOrder([
            'total_paid' => 10, 'base_total_paid' => 10, 'total_invoiced' => 10, 'base_total_invoiced' => 10,
            'store_to_base_rate' => 1, 'store_to_order_rate' => 1,
        ]);
        $order->getItemsCollection()->getFirstItem()->setQtyInvoiced(1)->save();
        $this->orders[] = $other = salesParityOrder();
        $itemId = salesParityItemId($other);
        $message = "Order item {$itemId} does not belong to this order";

        [$controller, $fields, $processor, $operation] = match ($document) {
            'invoice' => [Mage_Adminhtml_Sales_Order_InvoiceController::class, ['invoice' => ['items' => [$itemId => 1]]], InvoiceProcessor::class, 'order_invoice_create'],
            'shipment' => [Mage_Adminhtml_Sales_Order_ShipmentController::class, ['shipment' => ['items' => [$itemId => 1]]], ShipmentProcessor::class, 'order_shipment_create'],
            'credit memo' => [Mage_Adminhtml_Sales_Order_CreditmemoController::class, ['creditmemo' => ['items' => [$itemId => ['qty' => 1]], 'do_offline' => 1]], CreditMemoProcessor::class, 'order_creditmemo_create'],
        };
        $admin = AdminApiParity::admin($controller, 'save', ['order_id' => $order->getId()] + $fields);
        $api = salesParityApi($processor, $operation, ['orderId' => $order->getId()], ['items' => [['orderItemId' => $itemId, 'qty' => 1]]]);

        expect($admin['errors'])->toBe([$message])
            ->and($api)->toMatchArray(['status' => 422, 'errors' => [$message]]);
    })->with(['invoice', 'shipment', 'credit memo']);

    it('refuses a negative qty on both transports', function (): void {
        $this->orders[] = $order = salesParityOrder();
        $itemId = salesParityItemId($order);
        $message = "The qty of order item {$itemId} cannot be negative";

        $admin = AdminApiParity::admin(Mage_Adminhtml_Sales_Order_ShipmentController::class, 'save', [
            'order_id' => $order->getId(),
            'shipment' => ['items' => [$itemId => -1]],
        ]);
        $api = salesParityApi(ShipmentProcessor::class, 'order_shipment_create', ['orderId' => $order->getId()], [
            'items' => [['orderItemId' => $itemId, 'qty' => -1]],
        ]);

        expect($admin['errors'])->toBe([$message])
            ->and($api)->toMatchArray(['status' => 422, 'errors' => [$message]])
            ->and(Mage::getModel('sales/order')->load($order->getId())->getShipmentsCollection()->getSize())->toBe(0);
    });
});

describe('success on both transports', function (): void {
    it('cancels, holds and releases an order with the same stored rows', function (string $action, string $operation, array $data, string $state): void {
        $this->orders[] = $adminOrder = salesParityOrder($data);
        $this->orders[] = $apiOrder = salesParityOrder($data);

        $admin = AdminApiParity::admin(Mage_Adminhtml_Sales_OrderController::class, $action, ['order_id' => $adminOrder->getId()]);
        $api = salesParityApi(OrderProcessor::class, $operation, ['id' => $apiOrder->getId()]);

        $adminRows = salesParityStoredRows((int) $adminOrder->getId());
        expect($admin['errors'])->toBe([])
            ->and($api['errors'])->toBe([])
            ->and($adminRows)->toBe(salesParityStoredRows((int) $apiOrder->getId()))
            ->and($adminRows['order']['state'])->toBe($state);
    })->with([
        'cancel' => ['cancel', 'order_cancel', [], Mage_Sales_Model_Order::STATE_CANCELED],
        'hold' => ['hold', 'order_hold', [], Mage_Sales_Model_Order::STATE_HOLDED],
        'unhold' => ['unhold', 'order_unhold', [
            'state' => Mage_Sales_Model_Order::STATE_HOLDED, 'status' => 'holded',
            'hold_before_state' => Mage_Sales_Model_Order::STATE_NEW, 'hold_before_status' => 'pending',
        ], Mage_Sales_Model_Order::STATE_NEW],
    ]);

    it('invoices one item of an order with the same stored rows', function (): void {
        $this->orders[] = $adminOrder = salesParityOrder();
        $this->orders[] = $apiOrder = salesParityOrder();

        $admin = AdminApiParity::admin(Mage_Adminhtml_Sales_Order_InvoiceController::class, 'save', [
            'order_id' => $adminOrder->getId(),
            'invoice' => ['items' => [salesParityItemId($adminOrder) => 1]],
        ]);
        $api = salesParityApi(InvoiceProcessor::class, 'order_invoice_create', ['orderId' => $apiOrder->getId()], [
            'items' => [['orderItemId' => salesParityItemId($apiOrder), 'qty' => 1]],
        ]);

        $adminRows = salesParityStoredRows((int) $adminOrder->getId());
        expect($admin)->toBe(['success' => ['The invoice has been created.'], 'errors' => []])
            ->and($api['errors'])->toBe([])
            ->and($adminRows)->toBe(salesParityStoredRows((int) $apiOrder->getId()))
            ->and($adminRows['items'])->toBe([['qty_invoiced' => 1.0, 'qty_shipped' => 0.0, 'qty_refunded' => 0.0, 'qty_canceled' => 0.0]])
            ->and($adminRows['invoices'])->toHaveCount(1)
            ->and($adminRows['invoices'][0]['total_qty'])->toBe(1.0);
    });

    it('ships one item of an order with the same stored rows', function (): void {
        $this->orders[] = $adminOrder = salesParityOrder();
        $this->orders[] = $apiOrder = salesParityOrder();

        $admin = AdminApiParity::admin(Mage_Adminhtml_Sales_Order_ShipmentController::class, 'save', [
            'order_id' => $adminOrder->getId(),
            'shipment' => ['items' => [salesParityItemId($adminOrder) => 1]],
        ]);
        $api = salesParityApi(ShipmentProcessor::class, 'order_shipment_create', ['orderId' => $apiOrder->getId()], [
            'items' => [['orderItemId' => salesParityItemId($apiOrder), 'qty' => 1]],
        ]);

        $adminRows = salesParityStoredRows((int) $adminOrder->getId());
        expect($admin)->toBe(['success' => ['The shipment has been created.'], 'errors' => []])
            ->and($api['errors'])->toBe([])
            ->and($adminRows)->toBe(salesParityStoredRows((int) $apiOrder->getId()))
            ->and($adminRows['items'][0]['qty_shipped'])->toBe(1.0)
            ->and($adminRows['shipments'])->toHaveCount(1);
    });

    it('refunds an order offline with the same stored rows', function (): void {
        $this->orders[] = $adminOrder = salesParityPaidOrder();
        $this->orders[] = $apiOrder = salesParityPaidOrder();

        $admin = AdminApiParity::admin(Mage_Adminhtml_Sales_Order_CreditmemoController::class, 'save', [
            'order_id' => $adminOrder->getId(),
            'creditmemo' => ['do_offline' => 1],
        ]);
        $api = salesParityApi(CreditMemoProcessor::class, 'order_creditmemo_create', ['orderId' => $apiOrder->getId()], ['offlineRefund' => true]);

        $adminRows = salesParityStoredRows((int) $adminOrder->getId());
        expect($admin)->toBe(['success' => ['The credit memo has been created.'], 'errors' => []])
            ->and($api['errors'])->toBe([])
            ->and($adminRows)->toBe(salesParityStoredRows((int) $apiOrder->getId()))
            ->and($adminRows['order']['total_refunded'])->toBe(10.0)
            ->and($adminRows['items'][0]['qty_refunded'])->toBe(2.0)
            ->and($adminRows['creditmemos'])->toHaveCount(1);
    });
});

describe('rules that the admin pages get from the services', function (): void {
    it('takes an order comment without text when the comment names a status, as the admin form does', function (): void {
        $this->orders[] = $order = salesParityOrder();
        $service = Mage::getService('sales/order');

        $service->addNote($order, '', false, false, 'pending');

        expect(salesParityStoredRows((int) $order->getId())['comments'])->toBe([['', false]])
            ->and(fn() => $service->addNote($order, '  '))->toThrow(Mage_Core_Exception::class, 'Comment text is required');
    });

    it('saves the invoice and the shipment with a new comment, and only the comments of a credit memo', function (): void {
        $this->orders[] = $order = salesParityOrder();
        $service = Mage::getService('sales/order_document');
        $invoice = $service->invoice($order);
        $shipment = $service->ship($order);

        // A field that only the save of the document writes
        $service->addComment($invoice->setEmailSent(true), 'Invoice comment');
        $service->addComment($shipment->setEmailSent(true), 'Shipment comment');

        expect((bool) Mage::getModel('sales/order_invoice')->load($invoice->getId())->getEmailSent())->toBeTrue()
            ->and((bool) Mage::getModel('sales/order_shipment')->load($shipment->getId())->getEmailSent())->toBeTrue()
            ->and(Mage::getModel('sales/order_invoice')->load($invoice->getId())->getCommentsCollection()->getSize())->toBe(1)
            ->and(Mage::getModel('sales/order_shipment')->load($shipment->getId())->getCommentsCollection()->getSize())->toBe(1);
    });

    it('shows the error of an order that fails in the mass cancel for another reason than its state', function (): void {
        $this->orders[] = $order = salesParityOrder();
        $this->orders[] = $canceled = salesParityOrder(['state' => Mage_Sales_Model_Order::STATE_CANCELED, 'status' => 'canceled']);
        $failing = new class extends Mage_Sales_Service_Order {
            #[\Override]
            public function cancel(Mage_Sales_Model_Order $order, ?string $reason = null): Mage_Sales_Model_Order
            {
                if ($order->getState() === Mage_Sales_Model_Order::STATE_NEW) {
                    throw new Mage_Core_Exception('The gateway refused the void');
                }
                return parent::cancel($order, $reason);
            }
        };
        Mage::unregister('_service/sales/order');
        Mage::register('_service/sales/order', $failing);

        try {
            $admin = AdminApiParity::admin(Mage_Adminhtml_Sales_OrderController::class, 'massCancel', [
                'order_ids' => [$order->getId(), $canceled->getId()],
            ]);
        } finally {
            Mage::unregister('_service/sales/order');
        }

        expect($admin)->toBe(['success' => [], 'errors' => [
            "Order #{$order->getIncrementId()}: The gateway refused the void",
            'The order(s) cannot be canceled',
        ]]);
    });

    it('tells the admin that the email of a new invoice was not sent', function (): void {
        $this->orders[] = $order = salesParityOrder();
        $failing = new class extends Mage_Sales_Service_Order_Document {
            #[\Override]
            public function invoice(
                Mage_Sales_Model_Order $order,
                array $qtys = [],
                ?string $captureCase = null,
                ?string $comment = null,
                bool $notifyCustomer = false,
                bool $visibleOnFront = false,
                ?bool $sendEmail = null,
                bool $ship = false,
                array $tracks = [],
            ): Mage_Sales_Model_Order_Invoice {
                return parent::invoice($order, $qtys, $captureCase, $comment, $notifyCustomer, $visibleOnFront, $sendEmail, $ship, $tracks)
                    ->setData(self::EMAIL_FAILED, true)
                    ->setData(self::SHIPMENT_EMAIL_FAILED, true);
            }
        };
        Mage::unregister('_service/sales/order_document');
        Mage::register('_service/sales/order_document', $failing);

        try {
            $admin = AdminApiParity::admin(Mage_Adminhtml_Sales_Order_InvoiceController::class, 'save', ['order_id' => $order->getId()]);
        } finally {
            Mage::unregister('_service/sales/order_document');
        }

        expect($admin)->toBe([
            'success' => ['The invoice has been created.'],
            'errors' => ['Unable to send the invoice email.', 'Unable to send the shipment email.'],
        ]);
    });

    it('cancels a shipment through the document service, one time only', function (): void {
        $this->orders[] = $order = salesParityOrder();
        $shipment = Mage::getService('sales/order_document')->ship($order);

        $first = AdminApiParity::admin(Mage_Adminhtml_Sales_Order_ShipmentController::class, 'cancel', ['shipment_id' => $shipment->getId()]);
        Mage::unregister('current_shipment');
        $second = AdminApiParity::admin(Mage_Adminhtml_Sales_Order_ShipmentController::class, 'cancel', ['shipment_id' => $shipment->getId()]);

        $rows = salesParityStoredRows((int) $order->getId());
        expect($first)->toBe(['success' => ['The shipment has been canceled.'], 'errors' => []])
            ->and($second)->toBe(['success' => [], 'errors' => ['Cannot cancel the shipment.']])
            ->and($rows['items'][0]['qty_shipped'])->toBe(0.0)
            ->and($rows['shipments'][0]['shipment_status'])->toBe((float) Mage_Sales_Model_Order_Shipment::STATUS_CANCELED);
    });

    it('refuses to cancel a credit memo that is refunded', function (): void {
        $this->orders[] = $order = salesParityPaidOrder();
        $creditmemo = Mage::getService('sales/order_document')->refund($order, []);

        $admin = AdminApiParity::admin(Mage_Adminhtml_Sales_Order_CreditmemoController::class, 'cancel', ['creditmemo_id' => $creditmemo->getId()]);

        expect($admin)->toBe(['success' => [], 'errors' => ['The credit memo cannot be canceled in its current state']])
            ->and((int) Mage::getModel('sales/order_creditmemo')->load($creditmemo->getId())->getState())->toBe(Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED)
            ->and(salesParityStoredRows((int) $order->getId())['order']['total_refunded'])->toBe(10.0);
    });

    it('refuses a refund for an invoice that does not exist, on the field of the invoice ID', function (): void {
        $this->orders[] = $order = salesParityPaidOrder();

        try {
            Mage::getService('sales/order_document')->refund($order, [], invoice: Mage::getModel('sales/order_invoice'));
            $fields = null;
        } catch (Mage_Core_Exception_Input $e) {
            $fields = array_column($e->getErrors(), 'field');
        }
        $api = salesParityApi(CreditMemoProcessor::class, 'order_creditmemo_create', ['orderId' => $order->getId()], ['invoiceId' => 999999999]);

        // The service names the field of the model, and the API names the field as the client sends it
        expect($fields)->toBe(['invoice_id'])
            ->and($api)->toMatchArray(['status' => 422, 'errors' => ['The invoice does not belong to this order'], 'fields' => ['invoiceId']])
            ->and(Mage::getModel('sales/order')->load($order->getId())->getCreditmemosCollection()->getSize())->toBe(0);
    });

    it('marks the last comment of an order as notified when the order email is sent again', function (): void {
        $this->orders[] = $order = salesParityOrder();
        $order->addStatusHistoryComment('Waiting for the customer')->setIsCustomerNotified(false);
        $order->save();
        $store = Mage::app()->getStore(1);
        $enabled = $store->getConfig(Mage_Sales_Model_Order::XML_PATH_EMAIL_ENABLED);
        // The order of this test has no address, so the test sends no email
        $store->setConfig(Mage_Sales_Model_Order::XML_PATH_EMAIL_ENABLED, 0);

        try {
            Mage::getService('sales/order')->resendEmail($order);
        } finally {
            $store->setConfig(Mage_Sales_Model_Order::XML_PATH_EMAIL_ENABLED, $enabled);
        }

        expect(salesParityStoredRows((int) $order->getId())['comments'])->toBe([['Waiting for the customer', true]]);
    });

    it('changes the email of a guest order and writes who changed it', function (): void {
        $this->orders[] = $order = salesParityOrder();
        $this->orders[] = $customerOrder = salesParityOrder(['customer_is_guest' => 0]);
        $service = Mage::getService('sales/order');
        $oldEmail = $order->getCustomerEmail();

        expect($service->changeGuestEmail($order, ' new.guest@example.com ', 'pest-admin'))->toBeTrue()
            ->and($service->changeGuestEmail($order, 'new.guest@example.com', 'pest-admin'))->toBeFalse()
            ->and(fn() => $service->changeGuestEmail($order, 'not an email', 'pest-admin'))->toThrow(Mage_Core_Exception_Input::class)
            ->and(fn() => $service->changeGuestEmail($customerOrder, 'other@example.com', 'pest-admin'))->toThrow(Mage_Core_Exception_Conflict::class);

        $rows = salesParityStoredRows((int) $order->getId());
        expect(Mage::getModel('sales/order')->load($order->getId())->getCustomerEmail())->toBe('new.guest@example.com')
            ->and($rows['comments'])->toBe([["Customer email address changed from {$oldEmail} to new.guest@example.com by pest-admin.", false]]);
    });

    it('saves a changed order address, and refuses a region of another country', function (): void {
        $this->orders[] = $order = salesParityOrder();
        $address = Mage::getModel('sales/order_address')->setData([
            'parent_id' => $order->getId(), 'address_type' => 'billing',
            'firstname' => 'Parity', 'lastname' => 'Buyer', 'street' => '1 Main St', 'city' => 'Beverly Hills',
            'country_id' => 'US', 'region_id' => 12, 'postcode' => '90210', 'telephone' => '555-1234',
        ])->save();
        $service = Mage::getService('sales/order');

        $service->saveAddress($address->setCity('Los Angeles')->setStreet(['2 Side St', 'Suite 4']));
        $saved = Mage::getModel('sales/order_address')->load($address->getId());

        expect($saved->getCity())->toBe('Los Angeles')
            ->and($saved->getData('street'))->toBe("2 Side St\nSuite 4")
            ->and(fn() => $service->saveAddress($saved->setCountryId('DE')))->toThrow(Mage_Core_Exception_Input::class)
            ->and(Mage::getModel('sales/order_address')->load($address->getId())->getCountryId())->toBe('US');
    });
});
