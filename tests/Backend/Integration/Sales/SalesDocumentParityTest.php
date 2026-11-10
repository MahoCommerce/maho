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
 * the comments of a shipment, through Mage_Sales_Service_Order (#1348). Each test gives the same input to the
 * transports and compares the outcome.
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
    $service = Mage::getService('sales/order');
    $itemId = salesParityItemId($order);
    $track = Mage::getModel('sales/order_shipment_track')->setNumber('PARITY-1')->setCarrierCode('custom')->setTitle('Custom');

    return [$service->ship($order, [$itemId => 1], [$track]), $service->ship($order, [$itemId => 1])];
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
