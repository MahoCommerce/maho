<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Invoice Write Tests
 *
 * Coverage for invoice creation (full and partial, offline capture) and the
 * capture/void/cancel lifecycle endpoints.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('POST /api/rest/v2/orders/{orderId}/invoices', function (): void {

    it('requires authentication', function (): void {
        expect(apiPost('/api/rest/v2/orders/1/invoices', [])['status'])->toBeUnauthorized();
    });

    it('rejects a customer token (admin/invoices-create only)', function (): void {
        $response = apiPost('/api/rest/v2/orders/1/invoices', [], customerToken());
        expect($response['status'])->toBeIn([401, 403]);
    });

    it('returns 404 for non-existent order', function (): void {
        $response = apiPost('/api/rest/v2/orders/999999999/invoices', [], adminToken());
        expect($response['status'])->toBeNotFound();
    });

    it('rejects an invalid capture mode', function (): void {
        $orderId = seedInvoiceableOrder();
        if (!$orderId) {
            $this->markTestSkipped('Could not seed an invoiceable order in this store');
        }

        $response = apiPost("/api/rest/v2/orders/{$orderId}/invoices", [
            'capture' => 'bogus',
        ], adminToken());

        expect($response['status'])->toBe(400);
    });

    it('creates a full offline-captured invoice and lists it on the order', function (): void {
        $orderId = seedInvoiceableOrder();
        if (!$orderId) {
            $this->markTestSkipped('Could not seed an invoiceable order in this store');
        }

        $order = Mage::getModel('sales/order')->load($orderId);
        $expectedTotal = (float) $order->getGrandTotal();

        $response = apiPost("/api/rest/v2/orders/{$orderId}/invoices", [
            'capture' => 'offline',
            'comment' => 'Invoiced via API',
            'notifyCustomer' => false,
        ], adminToken());

        expect($response['status'])->toBeIn([200, 201]);
        $json = $response['json'];
        expect($json)->toHaveKey('id');
        expect($json['orderId'])->toBe($orderId);
        expect((float) $json['grandTotal'])->toEqualWithDelta($expectedTotal, 0.01);

        // New read fields
        expect($json)->toHaveKeys([
            'incrementId', 'orderIncrementId', 'subtotal', 'taxAmount', 'shippingAmount',
            'discountAmount', 'subtotalInclTax', 'shippingInclTax', 'totalQty',
            'baseGrandTotal', 'baseSubtotal', 'baseTaxAmount', 'baseShippingAmount',
            'baseDiscountAmount', 'storeId', 'emailSent', 'canVoidFlag',
            'isUsedForRefund', 'items', 'comments',
        ]);
        expect((float) $json['baseGrandTotal'])->toEqualWithDelta((float) $order->getBaseGrandTotal(), 0.01);
        expect($json['stateName'])->toBe('paid');

        expect($json['items'])->toBeArray()->not->toBeEmpty();
        $item = $json['items'][0];
        expect($item)->toHaveKeys(['orderItemId', 'productId', 'sku', 'qty', 'price', 'rowTotal', 'basePrice', 'baseRowTotal']);
        expect($item['orderItemId'])->toBeGreaterThan(0);

        expect($json['comments'])->toBeArray()->not->toBeEmpty();
        expect($json['comments'][0]['comment'])->toBe('Invoiced via API');

        // The order's invoice list now contains the created invoice
        $list = apiGet("/api/rest/v2/orders/{$orderId}/invoices", adminToken());
        expect($list['status'])->toBe(200);
        $invoices = $list['json']['invoices'] ?? getItems($list);
        $ids = array_map(static fn(array $inv): int => (int) $inv['id'], $invoices);
        expect($ids)->toContain((int) $json['id']);
    });

    it('creates a partial invoice, then invoices the remainder', function (): void {
        $orderId = seedInvoiceableOrder(2);
        if (!$orderId) {
            $this->markTestSkipped('Could not seed an invoiceable order in this store');
        }

        $order = Mage::getModel('sales/order')->load($orderId);
        if (count($order->getAllItems()) !== count($order->getAllVisibleItems())) {
            $this->markTestSkipped('Seeded order is composite: its dummy child lines drive the invoiced qty');
        }

        $orderItemId = null;
        foreach ($order->getAllVisibleItems() as $item) {
            $orderItemId = (int) $item->getId();
            break;
        }
        expect($orderItemId)->not->toBeNull();

        $partial = apiPost("/api/rest/v2/orders/{$orderId}/invoices", [
            'items' => [['orderItemId' => $orderItemId, 'qty' => 1]],
            'capture' => 'offline',
        ], adminToken());

        expect($partial['status'])->toBeIn([200, 201]);
        expect((float) $partial['json']['totalQty'])->toBe(1.0);

        $rest = apiPost("/api/rest/v2/orders/{$orderId}/invoices", [
            'capture' => 'offline',
        ], adminToken());

        expect($rest['status'])->toBeIn([200, 201]);
        expect((float) $rest['json']['totalQty'])->toBe(1.0);

        // Fully invoiced: a third invoice must be rejected
        $again = apiPost("/api/rest/v2/orders/{$orderId}/invoices", [], adminToken());
        expect($again['status'])->toBe(400);
    });

    it('rejects items with invalid orderItemId or qty', function (): void {
        $orderId = seedInvoiceableOrder();
        if (!$orderId) {
            $this->markTestSkipped('Could not seed an invoiceable order in this store');
        }

        $response = apiPost("/api/rest/v2/orders/{$orderId}/invoices", [
            'items' => [['orderItemId' => 0, 'qty' => 1]],
        ], adminToken());
        expect($response['status'])->toBe(400);

        $response = apiPost("/api/rest/v2/orders/{$orderId}/invoices", [
            'items' => [['orderItemId' => 123, 'qty' => 0]],
        ], adminToken());
        expect($response['status'])->toBe(400);

        // An item id that doesn't belong to the order must not be silently dropped
        $response = apiPost("/api/rest/v2/orders/{$orderId}/invoices", [
            'items' => [['orderItemId' => 999999999, 'qty' => 1]],
        ], adminToken());
        expect($response['status'])->toBe(400);
    });

    it('rejects a qty larger than the qty available to invoice', function (): void {
        $orderId = seedInvoiceableOrder();
        if (!$orderId) {
            $this->markTestSkipped('Could not seed an invoiceable order in this store');
        }

        $orderItemId = firstOrderItemId($orderId);
        expect($orderItemId)->not->toBeNull();

        $response = apiPost("/api/rest/v2/orders/{$orderId}/invoices", [
            'items' => [['orderItemId' => $orderItemId, 'qty' => 99]],
        ], adminToken());

        expect($response['status'])->toBe(400);
        expect(Mage::getModel('sales/order')->load($orderId)->canInvoice())->toBeTrue();
    });

    it('rejects a fractional qty on a non-decimal item', function (): void {
        $orderId = seedInvoiceableOrder();
        if (!$orderId) {
            $this->markTestSkipped('Could not seed an invoiceable order in this store');
        }

        $orderItemId = firstOrderItemId($orderId);
        expect($orderItemId)->not->toBeNull();

        $response = apiPost("/api/rest/v2/orders/{$orderId}/invoices", [
            'items' => [['orderItemId' => $orderItemId, 'qty' => 0.5]],
        ], adminToken());

        expect($response['status'])->toBe(400);

        // No zero-qty invoice may have been registered behind the rejection
        $list = apiGet("/api/rest/v2/orders/{$orderId}/invoices", adminToken());
        expect($list['json']['invoices'] ?? getItems($list))->toBeEmpty();
        expect(Mage::getModel('sales/order')->load($orderId)->canInvoice())->toBeTrue();
    });

    it('rejects malformed body types', function (): void {
        $orderId = seedInvoiceableOrder();
        if (!$orderId) {
            $this->markTestSkipped('Could not seed an invoiceable order in this store');
        }

        expect(apiPost("/api/rest/v2/orders/{$orderId}/invoices", ['items' => 'all'], adminToken())['status'])->toBe(400);
        expect(apiPost("/api/rest/v2/orders/{$orderId}/invoices", ['comment' => ['x']], adminToken())['status'])->toBe(400);
        expect(apiPost("/api/rest/v2/orders/{$orderId}/invoices", ['items' => ['nope']], adminToken())['status'])->toBe(400);
    });

});

describe('POST /api/rest/v2/invoices/{id}/capture|void|cancel', function (): void {

    it('requires authentication', function (): void {
        expect(apiPost('/api/rest/v2/invoices/1/capture', [])['status'])->toBeUnauthorized();
        expect(apiPost('/api/rest/v2/invoices/1/void', [])['status'])->toBeUnauthorized();
        expect(apiPost('/api/rest/v2/invoices/1/cancel', [])['status'])->toBeUnauthorized();
    });

    it('rejects a customer token (admin/invoices-write only)', function (): void {
        $token = customerToken();
        expect(apiPost('/api/rest/v2/invoices/1/capture', [], $token)['status'])->toBeIn([401, 403]);
        expect(apiPost('/api/rest/v2/invoices/1/void', [], $token)['status'])->toBeIn([401, 403]);
        expect(apiPost('/api/rest/v2/invoices/1/cancel', [], $token)['status'])->toBeIn([401, 403]);
    });

    it('returns 404 for a non-existent invoice', function (): void {
        expect(apiPost('/api/rest/v2/invoices/999999999/cancel', [], adminToken())['status'])->toBeNotFound();
    });

    it('cancels an open invoice', function (): void {
        $invoiceId = seedOpenInvoice();
        if (!$invoiceId) {
            $this->markTestSkipped('Could not seed an open (uncaptured) invoice in this store');
        }

        $response = apiPost("/api/rest/v2/invoices/{$invoiceId}/cancel", [], adminToken());

        expect($response['status'])->toBeIn([200, 201]);
        expect($response['json']['stateName'])->toBe('canceled');
        expect((int) Mage::getModel('sales/order_invoice')->load($invoiceId)->getState())
            ->toBe(Mage_Sales_Model_Order_Invoice::STATE_CANCELED);

        // Canceling twice is rejected
        expect(apiPost("/api/rest/v2/invoices/{$invoiceId}/cancel", [], adminToken())['status'])->toBe(400);
    });

    it('rejects capture and cancel on an already-paid offline invoice', function (): void {
        $orderId = seedInvoiceableOrder();
        if (!$orderId) {
            $this->markTestSkipped('Could not seed an invoiceable order in this store');
        }

        $created = apiPost("/api/rest/v2/orders/{$orderId}/invoices", [
            'capture' => 'offline',
        ], adminToken());
        expect($created['status'])->toBeIn([200, 201]);
        $invoiceId = (int) $created['json']['id'];
        expect($created['json']['stateName'])->toBe('paid');

        // Paid: not capturable, not cancelable; void requires a gateway transaction
        expect(apiPost("/api/rest/v2/invoices/{$invoiceId}/capture", [], adminToken())['status'])->toBe(400);
        expect(apiPost("/api/rest/v2/invoices/{$invoiceId}/cancel", [], adminToken())['status'])->toBe(400);
        expect(apiPost("/api/rest/v2/invoices/{$invoiceId}/void", [], adminToken())['status'])->toBe(400);
    });

});

// ---- setup helpers ----

/**
 * Id of the first visible item of an order, or null when it has none.
 */
function firstOrderItemId(int $orderId): ?int
{
    foreach (Mage::getModel('sales/order')->load($orderId)->getAllVisibleItems() as $item) {
        return (int) $item->getId();
    }
    return null;
}

/**
 * Register an invoice that stays in state OPEN, so the cancel endpoint has a
 * reachable success path. Built through the model layer because an offline
 * payment method pays the invoice on register() whatever capture case is
 * requested, unless the payment is flagged as pending.
 */
function seedOpenInvoice(): ?int
{
    try {
        $orderId = seedInvoiceableOrder();
        if (!$orderId) {
            return null;
        }

        $order = Mage::getModel('sales/order')->load($orderId);
        $order->getPayment()->setIsTransactionPending(true);

        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
        if (!$invoice->getTotalQty()) {
            return null;
        }
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::NOT_CAPTURE);
        $invoice->register();
        Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($order)
            ->save();

        return (int) $invoice->getState() === Mage_Sales_Model_Order_Invoice::STATE_OPEN
            ? (int) $invoice->getId()
            : null;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * SKU of a plain, individually visible simple product.
 *
 * The shared write_test_sku fixture only filters on type and status, so on a
 * sample-data install it can resolve to a configurable's variant. The cart API
 * promotes such a SKU to its configurable parent, and the resulting order then
 * carries a dummy child line that prepareInvoice() always invoices at its full
 * ordered qty, so a partial invoice no longer matches the requested qty.
 */
function invoiceableSku(): ?string
{
    static $sku = false;

    if ($sku !== false) {
        return $sku;
    }

    $sku = null;
    try {
        Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();

        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('sku')
            ->addAttributeToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['neq' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE])
            ->addAttributeToFilter('required_options', 0)
            ->setPageSize(20);

        foreach ($collection as $product) {
            if ($product->isSalable()) {
                $sku = (string) $product->getSku();
                break;
            }
        }
    } catch (\Throwable $e) {
        $sku = null;
    }

    $sku ??= fixtures('write_test_sku');

    return $sku;
}

/**
 * Place a fresh cash-on-delivery order through the API and return its id, ready
 * to be invoiced. Returns null (test skips) if the store can't complete the
 * checkout flow.
 */
function seedInvoiceableOrder(int $qty = 1): ?int
{
    try {
        $sku = invoiceableSku();
        if (!$sku) {
            return null;
        }

        $address = [
            'firstName' => 'Invoice',
            'lastName' => 'Tester',
            'street' => ['1 Invoice Way'],
            'city' => 'Los Angeles',
            'region' => 'California',
            'postcode' => '90210',
            'countryId' => 'US',
            'telephone' => '5550100',
        ];

        $create = apiPost('/api/rest/v2/carts', [], customerToken());
        if (!in_array($create['status'], [200, 201], true) || empty($create['json']['id'])) {
            return null;
        }
        $cartId = (int) $create['json']['id'];
        trackCreated('quote', $cartId);

        $add = apiPost("/api/rest/v2/carts/{$cartId}/items", ['sku' => $sku, 'qty' => $qty], customerToken());
        if (!in_array($add['status'], [200, 201], true)) {
            return null;
        }

        $place = apiPost('/api/rest/v2/orders', [
            'cartId' => $cartId,
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'paymentMethod' => 'cashondelivery',
            'shippingMethod' => 'freeshipping_freeshipping',
        ], customerToken());
        if (!in_array($place['status'], [200, 201], true) || empty($place['json']['id'])) {
            return null;
        }
        $orderId = (int) $place['json']['id'];
        trackCreated('order', $orderId);

        $order = Mage::getModel('sales/order')->load($orderId);
        return ($order->getId() && $order->canInvoice()) ? $orderId : null;
    } catch (\Throwable $e) {
        return null;
    }
}
