<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * Admin ACL gating tests.
 *
 * Verifies that AdminAclListener (REST) and per-handler AdminAcl::checkResource()
 * calls (GraphQL) consult Mage's admin ACL on every admin-token request,
 * mirroring the backend's `Mage_Adminhtml_Controller_Action::ADMIN_RESOURCE` /
 * `_isAllowed()` pattern.
 *
 * Three scenarios per surface:
 *   - admin with full ACL → 2xx
 *   - admin with restricted ACL → 403
 *   - admin token on a resource without ADMIN_RESOURCE → 403 (default-deny)
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Admin ACL, REST', function (): void {

    it('grants access when the admin role allows the resource', function (): void {
        $token = adminTokenWithAcl(['catalog/products'], 'pest_acl_grant');
        $response = apiGet('/api/rest/v2/products', $token);
        // Allowed → not 403. May be 200/206 with payload, or 404 if no
        // products exist in the test fixture; either way it isn't gated.
        expect($response['status'])->not->toBe(403);
    });

    it('denies (403) when the admin role does NOT include the resource', function (): void {
        // Role grants catalog only, credit-memo creation requires
        // sales/creditmemo and must be denied.
        $token = adminTokenWithAcl(['catalog/products'], 'pest_acl_deny_creditmemo');
        $response = apiPost(
            '/api/rest/v2/orders/1/credit-memos',
            ['comment' => 'should not get here'],
            $token,
        );
        expect($response['status'])->toBe(403);
    });

    it('denies (403) on a resource that declares no ADMIN_RESOURCE (default-deny)', function (): void {
        // Wishlist is customer-only and intentionally has no
        // ADMIN_RESOURCE constant. Even with full admin ACL, default-deny
        // must apply on a resource that hasn't opted in.
        $token = adminTokenWithAcl(['all'], 'pest_acl_default_deny');
        // The customer wishlist collection admits ROLE_ADMIN at the security
        // layer, so the request clears the firewall and reaches the ACL gate —
        // where default-deny applies because WishlistItem declares no
        // ADMIN_RESOURCE. (/wishlist-items is not a real route; use the actual
        // customer wishlist endpoint.)
        $response = apiGet('/api/rest/v2/customers/me/wishlist', $token);
        expect($response['status'])->toBe(403);
    });

    it('non-admin tokens are not affected by AdminAclListener', function (): void {
        // A service token with an explicit cms-pages permission must work,
        // AdminAclListener must skip non-admin tokens entirely.
        $token = serviceToken(['cms_pages/all']);
        $response = apiGet('/api/rest/v2/cms-pages', $token);
        expect($response['status'])->not->toBe(403);
    });
});

describe('Admin ACL, order actions', function (): void {

    it('denies (403) a document action without its order action permission', function (string $path, array $acl): void {
        $token = adminTokenWithAcl($acl, 'pest_acl_order_action_' . md5($path));
        $response = apiPost($path, [], $token);
        expect($response['status'])->toBe(403);
    })->with([
        'invoice create without invoice' => ['/api/rest/v2/orders/1/invoices', ['sales/invoice']],
        'invoice capture without capture' => ['/api/rest/v2/invoices/1/capture', ['sales/invoice']],
        'invoice void without cancel' => ['/api/rest/v2/invoices/1/void', ['sales/invoice']],
        'invoice cancel without cancel' => ['/api/rest/v2/invoices/1/cancel', ['sales/invoice']],
        'shipment create without ship' => ['/api/rest/v2/orders/1/shipments', ['sales/shipment']],
        'credit memo create without creditmemo' => ['/api/rest/v2/orders/1/credit-memos', ['sales/creditmemo']],
        'order hold without hold' => ['/api/rest/v2/orders/1/hold', ['sales/order']],
        'order unhold without unhold' => ['/api/rest/v2/orders/1/unhold', ['sales/order']],
        'order cancel without cancel' => ['/api/rest/v2/orders/1/cancel', ['sales/order']],
        'order comment without comment' => ['/api/rest/v2/orders/1/comments', ['sales/order']],
    ]);

    it('admits a document action with its order action permission', function (): void {
        $token = adminTokenWithAcl(['sales/invoice', 'sales/order/actions/capture'], 'pest_acl_order_action_capture');
        $response = apiPost('/api/rest/v2/invoices/999999999/capture', [], $token);
        expect($response['status'])->not->toBe(403);
    });
});

describe('Admin ACL, GraphQL', function (): void {

    $query = '{ orders(first: 1) { edges { node { id } } } }';

    it('admits an admin whose role allows the resource', function () use ($query): void {
        $response = gqlQuery($query, [], adminTokenWithAcl(['sales/order'], 'pest_acl_gql_grant'));
        expect($response['json'])->not->toHaveKey('errors');
    });

    it('denies an admin whose role does not allow the resource', function () use ($query): void {
        $response = gqlQuery($query, [], adminTokenWithAcl(['catalog/products'], 'pest_acl_gql_deny'));
        expect($response['json'])->toHaveKey('errors')
            ->and($response['json']['data']['orders'] ?? null)->toBeNull();
    });
});
