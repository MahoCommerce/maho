<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ApiV2Helper;

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

/**
 * Create an admin role granting only the listed ACL paths, plus an admin
 * user assigned to that role. Returns a JWT for the user.
 *
 * @param list<string> $allowedAclPaths e.g. ['catalog/products', 'sales']
 */
function adminTokenWithAcl(array $allowedAclPaths, string $username): string
{
    ApiV2Helper::ensureMahoBootstrapped();

    // The JWT secret is auto-generated on the kernel's first HTTP boot.
    // Trigger it with a public no-auth request before issuing tokens,
    // mirrors what apiGet/apiPost do implicitly in other tests, but those
    // tests issue tokens after their first HTTP call.
    static $kernelBooted = false;
    if (!$kernelBooted) {
        apiGet('/api/rest/v2/store-config');
        Mage::app()->getCache()->cleanType('config');
        Mage::app()->reinitStores();
        $kernelBooted = true;
    }

    /** @var Mage_Admin_Model_Role $role */
    $role = Mage::getModel('admin/role');
    $role->setData([
        'role_name' => $username . '_role',
        'role_type' => Mage_Admin_Model_Acl::ROLE_TYPE_GROUP,
        'parent_id' => 0,
    ])->save();
    trackCreated('admin_role', (int) $role->getId());

    Mage::getModel('admin/rules')
        ->setRoleId($role->getId())
        ->setResources($allowedAclPaths)
        ->saveRel();

    /** @var Mage_Admin_Model_User $user */
    $user = Mage::getModel('admin/user');
    $user->setData([
        'username' => $username,
        'firstname' => 'Pest',
        'lastname' => 'Acl',
        'email' => $username . '@example.test',
        'password' => 'pest-acl-password-1234',
        'is_active' => 1,
    ])->save();
    trackCreated('admin_user', (int) $user->getId());

    Mage::getModel('admin/user')
        ->setRoleId($role->getId())
        ->setUserId($user->getId())
        ->add();

    return ApiV2Helper::generateToken([
        'sub' => 'admin_' . $user->getId(),
        'admin_id' => (int) $user->getId(),
        'email' => $user->getEmail(),
        'type' => 'admin',
        'roles' => ['ROLE_ADMIN'],
    ]);
}

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
        $response = apiGet('/api/rest/v2/wishlist-items', $token);
        expect($response['status'])->toBe(403);
    });

    it('non-admin tokens are not affected by AdminAclListener', function (): void {
        // A service token (ROLE_API_USER) with explicit cms-pages permission
        // must work, AdminAclListener must skip non-admin tokens entirely.
        $token = serviceToken(['cms_pages/all']);
        $response = apiGet('/api/rest/v2/cms-pages', $token);
        expect($response['status'])->not->toBe(403);
    });
});
