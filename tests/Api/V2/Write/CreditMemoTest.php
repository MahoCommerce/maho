<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Credit Memo Tests (WRITE)
 *
 * Regression coverage for refund-total collection on the REST create path. The
 * creditmemo total collectors accumulate into grand total and never reset, so
 * collecting twice doubles every total. A doubled full refund exceeds the
 * order's refundable balance and is rejected — so "a full refund succeeds and
 * equals the order total" is the signal that totals were collected exactly once.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('POST /api/rest/v2/orders/{orderId}/credit-memos', function (): void {

    it('requires authentication', function (): void {
        expect(apiPost('/api/rest/v2/orders/1/credit-memos', ['items' => []])['status'])->toBeUnauthorized();
    });

    it('rejects a customer token (admin/orders-write only)', function (): void {
        $response = apiPost('/api/rest/v2/orders/1/credit-memos', ['items' => []], customerToken());
        expect($response['status'])->toBeIn([401, 403]);
    });

    it('refunds the full order total without doubling it', function (): void {
        $orderId = seedRefundableOrder();
        if (!$orderId) {
            $this->markTestSkipped('Could not seed an invoiced, refundable order in this store');
        }

        $order = Mage::getModel('sales/order')->load($orderId);
        $expected = (float) $order->getBaseGrandTotal();

        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'orderItemId' => (int) $item->getId(),
                'qty' => (float) $item->getQtyOrdered(),
            ];
        }

        $response = apiPost("/api/rest/v2/orders/{$orderId}/credit-memos", [
            'items' => $items,
            'offlineRefund' => true,
        ], adminToken());

        // A doubled memo total is rejected here as exceeding the refundable
        // balance; success means totals were collected exactly once.
        expect($response['status'])->toBeIn([200, 201]);
        expect((float) ($response['json']['baseGrandTotal'] ?? 0))->toEqualWithDelta($expected, 0.01);
    });

    it('refunds an adjustment alone when items is an empty list', function (): void {
        $orderId = seedRefundableOrder();
        if (!$orderId) {
            $this->markTestSkipped('Could not seed an invoiced, refundable order in this store');
        }

        $response = apiPost("/api/rest/v2/orders/{$orderId}/credit-memos", [
            'items' => [],
            'adjustmentPositive' => 5,
            'offlineRefund' => true,
        ], adminToken());

        expect($response['status'])->toBeIn([200, 201]);
        expect((float) ($response['json']['baseGrandTotal'] ?? 0))->toEqualWithDelta(5.0, 0.01);
        expect($response['json']['items'] ?? [])->toBeEmpty();

        // The goodwill refund must leave the item refundable for a later return.
        $order = Mage::getModel('sales/order')->load($orderId);
        expect($order->canCreditmemo())->toBeTrue();
        foreach ($order->getAllVisibleItems() as $item) {
            expect((float) $item->getQtyRefunded())->toEqualWithDelta(0.0, 0.001);
        }
    });

    it('refunds no item when every qty is zero', function (): void {
        $orderId = seedRefundableOrder();
        if (!$orderId) {
            $this->markTestSkipped('Could not seed an invoiced, refundable order in this store');
        }

        $order = Mage::getModel('sales/order')->load($orderId);
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = ['orderItemId' => (int) $item->getId(), 'qty' => 0];
        }

        $response = apiPost("/api/rest/v2/orders/{$orderId}/credit-memos", [
            'items' => $items,
            'adjustmentPositive' => 5,
            'offlineRefund' => true,
        ], adminToken());

        expect($response['status'])->toBeIn([200, 201]);
        expect((float) ($response['json']['baseGrandTotal'] ?? 0))->toEqualWithDelta(5.0, 0.01);
    });

    it('does not refund shipping on an adjustment-only memo', function (): void {
        $orderId = seedRefundableOrder('flatrate_flatrate');
        if (!$orderId) {
            $this->markTestSkipped('Could not seed an invoiced, refundable order in this store');
        }

        $order = Mage::getModel('sales/order')->load($orderId);
        if ((float) $order->getBaseShippingAmount() <= 0) {
            $this->markTestSkipped('Seeded order carries no shipping charge');
        }

        $response = apiPost("/api/rest/v2/orders/{$orderId}/credit-memos", [
            'items' => [],
            'adjustmentPositive' => 5,
            'offlineRefund' => true,
        ], adminToken());

        expect($response['status'])->toBeIn([200, 201]);
        expect((float) ($response['json']['baseGrandTotal'] ?? 0))->toEqualWithDelta(5.0, 0.01);
        expect((float) ($response['json']['baseShippingAmount'] ?? 0))->toEqualWithDelta(0.0, 0.01);
    });

    it('rejects an empty items list with nothing to refund', function (): void {
        $orderId = seedRefundableOrder();
        if (!$orderId) {
            $this->markTestSkipped('Could not seed an invoiced, refundable order in this store');
        }

        $response = apiPost("/api/rest/v2/orders/{$orderId}/credit-memos", [
            'items' => [],
            'offlineRefund' => true,
        ], adminToken());

        expect($response['status'])->toBe(400);
    });

    it('refunds every item when items is omitted', function (): void {
        $orderId = seedRefundableOrder();
        if (!$orderId) {
            $this->markTestSkipped('Could not seed an invoiced, refundable order in this store');
        }

        $order = Mage::getModel('sales/order')->load($orderId);
        $expected = (float) $order->getBaseGrandTotal();

        $response = apiPost("/api/rest/v2/orders/{$orderId}/credit-memos", [
            'offlineRefund' => true,
        ], adminToken());

        expect($response['status'])->toBeIn([200, 201]);
        expect((float) ($response['json']['baseGrandTotal'] ?? 0))->toEqualWithDelta($expected, 0.01);
    });

    it('rejects an order item that belongs to another order', function (): void {
        $orderId = seedRefundableOrder();
        if (!$orderId) {
            $this->markTestSkipped('Could not seed an invoiced, refundable order in this store');
        }

        $response = apiPost("/api/rest/v2/orders/{$orderId}/credit-memos", [
            'items' => [['orderItemId' => 999999999, 'qty' => 1]],
            'offlineRefund' => true,
        ], adminToken());

        expect($response['status'])->toBe(400);
    });

});

// ---- setup helper ----

/**
 * Place a fresh order through the API, invoice it (offline) via the model layer
 * so it becomes refundable, and return its id. Returns null (test skips) if the
 * store can't complete the checkout/invoice flow.
 */
function seedRefundableOrder(string $shippingMethod = 'freeshipping_freeshipping'): ?int
{
    try {
        $sku = fixtures('write_test_sku');
        if (!$sku) {
            return null;
        }

        $address = [
            'firstName' => 'Refund',
            'lastName' => 'Tester',
            'street' => ['1 Refund Way'],
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

        $add = apiPost("/api/rest/v2/carts/{$cartId}/items", ['sku' => $sku, 'qty' => 1], customerToken());
        if (!in_array($add['status'], [200, 201], true)) {
            return null;
        }

        $place = apiPost('/api/rest/v2/orders', [
            'cartId' => $cartId,
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'paymentMethod' => 'cashondelivery',
            'shippingMethod' => $shippingMethod,
        ], customerToken());
        if (!in_array($place['status'], [200, 201], true) || empty($place['json']['id'])) {
            return null;
        }
        $orderId = (int) $place['json']['id'];
        trackCreated('order', $orderId);

        $order = Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            return null;
        }
        if ($order->canCreditmemo()) {
            return $orderId;
        }
        if (!$order->canInvoice()) {
            return null;
        }

        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
        if (!$invoice->getTotalQty()) {
            return null;
        }
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($order)
            ->save();

        return $order->canCreditmemo() ? $orderId : null;
    } catch (\Throwable $e) {
        return null;
    }
}
