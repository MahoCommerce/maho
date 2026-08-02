<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Sales Document Field Coverage Tests
 *
 * Read-only assertions that invoices, shipments and credit memos expose the
 * full document detail (totals, base totals, line items with order-item
 * mapping, comments). Tests skip when the database has no such documents.
 *
 * @group read
 */

describe('Invoice read fields', function (): void {

    it('exposes totals, base totals, items and comments on the order invoice list', function (): void {
        $row = salesDocFindRow('sales_flat_invoice', ['entity_id', 'order_id']);
        if (!$row) {
            $this->markTestSkipped('No invoices found in database');
        }

        $response = apiGet("/api/rest/v2/orders/{$row['order_id']}/invoices", adminToken());
        expect($response['status'])->toBe(200);

        $invoices = $response['json']['invoices'] ?? getItems($response);
        expect($invoices)->toBeArray()->not->toBeEmpty();

        $invoice = null;
        foreach ($invoices as $candidate) {
            if ((int) ($candidate['id'] ?? 0) === (int) $row['entity_id']) {
                $invoice = $candidate;
            }
        }
        expect($invoice)->not->toBeNull();

        expect($invoice)->toHaveKeys([
            'incrementId', 'orderId', 'orderIncrementId', 'storeId',
            'grandTotal', 'subtotal', 'taxAmount', 'shippingAmount', 'discountAmount',
            'subtotalInclTax', 'shippingInclTax', 'totalQty',
            'baseGrandTotal', 'baseSubtotal', 'baseTaxAmount', 'baseShippingAmount', 'baseDiscountAmount',
            'emailSent', 'canVoidFlag', 'isUsedForRefund',
            'state', 'stateName', 'createdAt', 'items', 'comments',
        ]);

        expect($invoice['items'])->toBeArray();
        if ($invoice['items'] !== []) {
            expect($invoice['items'][0])->toHaveKeys([
                'orderItemId', 'productId', 'sku', 'name', 'qty',
                'price', 'priceInclTax', 'rowTotal', 'rowTotalInclTax',
                'taxAmount', 'discountAmount',
                'basePrice', 'basePriceInclTax', 'baseRowTotal', 'baseRowTotalInclTax',
                'baseTaxAmount', 'baseDiscountAmount',
            ]);
        }
    });

});

describe('Shipment read fields', function (): void {

    it('exposes weight, status, packages, comments and full item detail', function (): void {
        $row = salesDocFindRow('sales_flat_shipment', ['entity_id']);
        if (!$row) {
            $this->markTestSkipped('No shipments found in database');
        }

        $response = apiGet("/api/rest/v2/shipments/{$row['entity_id']}", adminToken());
        expect($response['status'])->toBe(200);

        $json = $response['json'];
        expect($json)->toHaveKeys([
            'orderId', 'incrementId', 'orderIncrementId', 'totalQty',
            'totalWeight', 'storeId', 'emailSent',
            'createdAt', 'packages', 'comments', 'items', 'tracks',
        ]);
        expect($json['packages'])->toBeArray();
        expect($json['comments'])->toBeArray();

        expect($json['items'])->toBeArray();
        if ($json['items'] !== []) {
            expect($json['items'][0])->toHaveKeys([
                'id', 'orderItemId', 'productId', 'sku', 'name', 'qty',
                'price', 'rowTotal', 'weight',
            ]);
            expect($json['items'][0]['orderItemId'])->toBeGreaterThan(0);
        }
    });

});

describe('Credit memo read fields', function (): void {

    it('exposes totals, base totals, adjustments, comments and full item detail', function (): void {
        $row = salesDocFindRow('sales_flat_creditmemo', ['entity_id']);
        if (!$row) {
            $this->markTestSkipped('No credit memos found in database');
        }

        $response = apiGet("/api/rest/v2/credit-memos/{$row['entity_id']}", adminToken());
        expect($response['status'])->toBe(200);

        $json = $response['json'];
        expect($json)->toHaveKeys([
            'orderId', 'incrementId', 'orderIncrementId', 'storeId',
            'grandTotal', 'baseGrandTotal', 'subtotal', 'taxAmount', 'shippingAmount', 'discountAmount',
            'subtotalInclTax', 'shippingInclTax', 'shippingTaxAmount', 'hiddenTaxAmount',
            'baseSubtotal', 'baseTaxAmount', 'baseShippingAmount', 'baseDiscountAmount',
            'adjustment', 'baseAdjustment', 'adjustmentPositive', 'adjustmentNegative',
            'emailSent', 'state', 'createdAt', 'items', 'comments',
        ]);
        expect($json['comments'])->toBeArray();

        expect($json['items'])->toBeArray();
        if ($json['items'] !== []) {
            expect($json['items'][0])->toHaveKeys([
                'orderItemId', 'productId', 'sku', 'name', 'qty',
                'price', 'priceInclTax', 'rowTotal', 'rowTotalInclTax',
                'taxAmount', 'discountAmount',
                'basePrice', 'baseRowTotal', 'baseTaxAmount', 'baseDiscountAmount',
                'backToStock',
            ]);
        }
    });

});

// ---- lookup helper ----

/**
 * @param list<string> $columns
 * @return array<string, mixed>|null
 */
function salesDocFindRow(string $table, array $columns): ?array
{
    try {
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $row = $read->fetchRow(
            $read->select()->from($table, $columns)->order('entity_id DESC')->limit(1),
        );
        return $row ?: null;
    } catch (\Throwable $e) {
        return null;
    }
}
