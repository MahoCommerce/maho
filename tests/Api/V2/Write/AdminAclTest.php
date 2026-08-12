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
