<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Invoice Tests
 *
 * Tests for invoice endpoints - PROTECTED (auth required).
 * Invoices are accessed through orders.
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('API v2 Invoices', function (): void {

    describe('GET /api/rest/v2/orders/{orderId}/invoices', function (): void {

        it('lets a customer list the invoices of an own order and hides the order of another customer', function (): void {
            $orderId = fixtures('customer_order_id');
            $invoiceId = fixtures('customer_invoice_id');
            $otherOrderId = fixtures('other_customer_order_id');
            if (!$orderId || !$invoiceId || !$otherOrderId) {
                $this->markTestSkipped('No customer and other customer invoice fixtures');
            }

            $own = apiGet("/api/rest/v2/orders/{$orderId}/invoices", customerToken());
            expect($own['status'])->toBe(200);
            $invoices = $own['json']['invoices'] ?? getItems($own);
            expect(array_map('intval', array_column($invoices, 'id')))->toContain((int) $invoiceId);

            expect(apiGet("/api/rest/v2/orders/{$otherOrderId}/invoices", customerToken())['status'])->toBe(404);
        });

        it('returns 404 for non-existent order', function (): void {
            $invalidId = fixtures('invalid_order_id') ?? 999999;
            $response = apiGet("/api/rest/v2/orders/{$invalidId}/invoices", adminToken());

            expect($response['status'])->toBe(404);
        });

    });

    describe('GET /api/rest/v2/customers/me/orders/{orderId}/invoices - customer access', function (): void {

        it('allows authenticated customer to list their order invoices', function (): void {
            $orderId = fixtures('customer_order_id');
            $invoiceId = fixtures('customer_invoice_id');

            if (!$orderId || !$invoiceId) {
                $this->markTestSkipped('No customer_order_id or customer_invoice_id configured in fixtures');
            }

            $response = apiGet("/api/rest/v2/customers/me/orders/{$orderId}/invoices", customerToken());

            expect($response['status'])->toBe(200);
            $invoices = $response['json']['invoices'] ?? getItems($response);
            expect(array_map('intval', array_column($invoices, 'id')))->toContain((int) $invoiceId);
        });

        it('returns 404 for orders not belonging to customer', function (): void {
            $orderId = fixtures('other_customer_order_id');

            if (!$orderId) {
                $this->markTestSkipped('No other_customer_order_id configured in fixtures');
            }

            $response = apiGet("/api/rest/v2/customers/me/orders/{$orderId}/invoices", customerToken());

            expect($response['status'])->toBe(404);
        });

    });

    describe('GET /api/rest/v2/orders/{orderId}/invoices/{invoiceId}/pdf - PDF download', function (): void {

        it('returns PDF with valid admin token', function (): void {
            $orderId = fixtures('customer_order_id');
            $invoiceId = fixtures('customer_invoice_id');

            if (!$orderId || !$invoiceId) {
                $this->markTestSkipped('No customer_order_id or customer_invoice_id configured in fixtures');
            }

            $response = apiGetRaw("/api/rest/v2/orders/{$orderId}/invoices/{$invoiceId}/pdf", adminToken());

            expect($response['status'])->toBe(200);
            expect($response['headers']['content-type'][0] ?? '')->toContain('application/pdf');
        });

        it('returns 404 for non-existent invoice', function (): void {
            $orderId = fixtures('order_id') ?? 1;
            $invalidInvoiceId = 999999;

            $response = apiGet("/api/rest/v2/orders/{$orderId}/invoices/{$invalidInvoiceId}/pdf", adminToken());

            expect($response['status'])->toBe(404);
        });

    });

    describe('GET /api/rest/v2/customers/me/orders/{orderId}/invoices/{invoiceId}/pdf - customer PDF download', function (): void {

        it('allows customer to download their invoice PDF', function (): void {
            $orderId = fixtures('customer_order_id');
            $invoiceId = fixtures('customer_invoice_id');

            if (!$orderId || !$invoiceId) {
                $this->markTestSkipped('No customer_order_id or customer_invoice_id configured in fixtures');
            }

            $response = apiGetRaw("/api/rest/v2/customers/me/orders/{$orderId}/invoices/{$invoiceId}/pdf", customerToken());

            expect($response['status'])->toBe(200);
            expect($response['headers']['content-type'][0] ?? '')->toContain('application/pdf');
        });

        it('returns 404 for invoice not belonging to customer', function (): void {
            $orderId = fixtures('other_customer_order_id');
            $invoiceId = fixtures('other_customer_invoice_id');

            if (!$orderId || !$invoiceId) {
                $this->markTestSkipped('No other_customer_order_id or other_customer_invoice_id configured in fixtures');
            }

            $response = apiGet("/api/rest/v2/customers/me/orders/{$orderId}/invoices/{$invoiceId}/pdf", customerToken());

            expect($response['status'])->toBe(404);
        });

    });

});
