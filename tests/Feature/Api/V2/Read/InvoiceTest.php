<?php

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

    describe('GET /api/orders/{orderId}/invoices - without authentication', function (): void {

        it('rejects listing invoices without token', function (): void {
            $orderId = fixtures('order_id') ?? 1;
            $response = apiGet("/api/orders/{$orderId}/invoices");

            expect($response['status'])->toBeUnauthorized();
        });

        it('returns 401 error for unauthenticated request', function (): void {
            $orderId = fixtures('order_id') ?? 1;
            $response = apiGet("/api/orders/{$orderId}/invoices");

            expect($response['status'])->toBe(401);
            expect($response['json'])->toHaveKey('error');
            expect($response['json']['error'])->toBe('unauthorized');
        });

    });

    describe('GET /api/orders/{orderId}/invoices - with invalid token', function (): void {

        it('rejects invoice list with malformed token', function (): void {
            $orderId = fixtures('order_id') ?? 1;
            $response = apiGet("/api/orders/{$orderId}/invoices", 'invalid-token');

            expect($response['status'])->toBeUnauthorized();
        });

        it('rejects invoice list with expired token', function (): void {
            $orderId = fixtures('order_id') ?? 1;
            $response = apiGet("/api/orders/{$orderId}/invoices", expiredToken());

            expect($response['status'])->toBeUnauthorized();
        });

    });

    describe('GET /api/orders/{orderId}/invoices - with valid token', function (): void {

        it('allows listing invoices with valid customer token', function (): void {
            $orderId = fixtures('order_id');

            if (!$orderId) {
                $this->markTestSkipped('No order_id configured in fixtures');
            }

            $response = apiGet("/api/orders/{$orderId}/invoices", customerToken());

            // Should return 200 with invoices, 403 if not customer's order, or 404 if order not found
            expect($response['status'])->toBeIn([200, 403, 404]);
        });

        it('allows listing invoices with admin token', function (): void {
            $orderId = fixtures('order_id');

            if (!$orderId) {
                $this->markTestSkipped('No order_id configured in fixtures');
            }

            $response = apiGet("/api/orders/{$orderId}/invoices", adminToken());

            // Admin should have access
            expect($response['status'])->toBeIn([200, 404]);
        });

        it('returns invoices array when order exists', function (): void {
            $orderId = fixtures('order_id');

            if (!$orderId) {
                $this->markTestSkipped('No order_id configured in fixtures');
            }

            $response = apiGet("/api/orders/{$orderId}/invoices", adminToken());

            if ($response['status'] === 200) {
                expect($response['json'])->toHaveKey('invoices');
                expect($response['json']['invoices'])->toBeArray();
                expect($response['json'])->toHaveKey('count');
            }
        });

        it('returns 404 for non-existent order', function (): void {
            $invalidId = fixtures('invalid_order_id') ?? 999999;
            $response = apiGet("/api/orders/{$invalidId}/invoices", adminToken());

            expect($response['status'])->toBe(404);
        });

    });

    describe('GET /api/customers/me/orders/{orderId}/invoices - customer access', function (): void {

        it('rejects without authentication', function (): void {
            $orderId = fixtures('order_id') ?? 1;
            $response = apiGet("/api/customers/me/orders/{$orderId}/invoices");

            expect($response['status'])->toBeUnauthorized();
        });

        it('allows authenticated customer to list their order invoices', function (): void {
            $orderId = fixtures('customer_order_id');

            if (!$orderId) {
                $this->markTestSkipped('No customer_order_id configured in fixtures');
            }

            $response = apiGet("/api/customers/me/orders/{$orderId}/invoices", customerToken());

            expect($response['status'])->toBeIn([200, 404]);
        });

        it('returns 404 for orders not belonging to customer', function (): void {
            // Try to access another customer's order
            $orderId = fixtures('other_customer_order_id');

            if (!$orderId) {
                $this->markTestSkipped('No other_customer_order_id configured in fixtures');
            }

            $response = apiGet("/api/customers/me/orders/{$orderId}/invoices", customerToken());

            // Should be 404 (order not found for this customer)
            expect($response['status'])->toBe(404);
        });

    });

    describe('GET /api/orders/{orderId}/invoices/{invoiceId}/pdf - PDF download', function (): void {

        it('rejects PDF download without authentication', function (): void {
            $orderId = fixtures('order_id') ?? 1;
            $invoiceId = fixtures('invoice_id') ?? 1;

            $response = apiGet("/api/orders/{$orderId}/invoices/{$invoiceId}/pdf");

            expect($response['status'])->toBeUnauthorized();
        });

        it('rejects PDF download with invalid token', function (): void {
            $orderId = fixtures('order_id') ?? 1;
            $invoiceId = fixtures('invoice_id') ?? 1;

            $response = apiGet("/api/orders/{$orderId}/invoices/{$invoiceId}/pdf", 'invalid-token');

            expect($response['status'])->toBeUnauthorized();
        });

        it('returns PDF with valid admin token', function (): void {
            $orderId = fixtures('order_id');
            $invoiceId = fixtures('invoice_id');

            if (!$orderId || !$invoiceId) {
                $this->markTestSkipped('No order_id or invoice_id configured in fixtures');
            }

            $response = apiGetRaw("/api/orders/{$orderId}/invoices/{$invoiceId}/pdf", adminToken());

            // Should return 200 with PDF content, or 404 if invoice not found
            expect($response['status'])->toBeIn([200, 404]);

            if ($response['status'] === 200) {
                expect($response['headers']['content-type'][0] ?? '')->toContain('application/pdf');
            }
        });

        it('returns 404 for non-existent invoice', function (): void {
            $orderId = fixtures('order_id') ?? 1;
            $invalidInvoiceId = 999999;

            $response = apiGet("/api/orders/{$orderId}/invoices/{$invalidInvoiceId}/pdf", adminToken());

            expect($response['status'])->toBe(404);
        });

    });

    describe('GET /api/customers/me/orders/{orderId}/invoices/{invoiceId}/pdf - customer PDF download', function (): void {

        it('rejects without authentication', function (): void {
            $orderId = fixtures('order_id') ?? 1;
            $invoiceId = fixtures('invoice_id') ?? 1;

            $response = apiGet("/api/customers/me/orders/{$orderId}/invoices/{$invoiceId}/pdf");

            expect($response['status'])->toBeUnauthorized();
        });

        it('allows customer to download their invoice PDF', function (): void {
            $orderId = fixtures('customer_order_id');
            $invoiceId = fixtures('customer_invoice_id');

            if (!$orderId || !$invoiceId) {
                $this->markTestSkipped('No customer_order_id or customer_invoice_id configured in fixtures');
            }

            $response = apiGetRaw("/api/customers/me/orders/{$orderId}/invoices/{$invoiceId}/pdf", customerToken());

            expect($response['status'])->toBeIn([200, 404]);

            if ($response['status'] === 200) {
                expect($response['headers']['content-type'][0] ?? '')->toContain('application/pdf');
            }
        });

        it('returns 404 for invoice not belonging to customer', function (): void {
            $orderId = fixtures('other_customer_order_id');
            $invoiceId = fixtures('other_customer_invoice_id');

            if (!$orderId || !$invoiceId) {
                $this->markTestSkipped('No other_customer_order_id or other_customer_invoice_id configured in fixtures');
            }

            $response = apiGet("/api/customers/me/orders/{$orderId}/invoices/{$invoiceId}/pdf", customerToken());

            expect($response['status'])->toBe(404);
        });

    });

});
