<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 admin users and roles: read only, without credentials.
 *
 * @group write
 */

const AUSR_PATH = '/api/rest/v2/admin-users';
const AROL_PATH = '/api/rest/v2/admin-roles';

const AUSR_SECRET_KEYS = ['password', 'apiKey', 'api_key', 'rpToken', 'rp_token', 'rpTokenCreatedAt', 'twofaSecret', 'twofa_secret', 'twofaEnabled', 'extra', 'passkeyPublicKey', 'passkeyCredentialIdHash'];

afterAll(function (): void {
    cleanupTestData();
});

function ausrMembers(array $response): array
{
    return $response['json']['member'] ?? $response['json']['hydra:member'] ?? [];
}

describe('Admin user and role access', function (): void {

    it('denies reads without authentication', function (): void {
        expect(apiGet(AUSR_PATH)['status'])->toBe(401);
        expect(apiGet(AROL_PATH)['status'])->toBe(401);
    });

    it('denies a token without the permission and a customer', function (): void {
        $other = serviceToken(['cms-pages/read']);
        expect(apiGet(AUSR_PATH, $other)['status'])->toBeForbidden();
        expect(apiGet(AROL_PATH, $other)['status'])->toBeForbidden();
        expect(apiGet(AUSR_PATH, customerToken())['status'])->toBeForbidden();
    });

    it('denies an admin whose role does not allow the permissions section', function (): void {
        $token = adminTokenWithAcl(['catalog/products'], 'pest_ausr_acl_deny');
        expect(apiGet(AUSR_PATH, $token)['status'])->toBe(403);
        expect(apiGet(AROL_PATH, $token)['status'])->toBe(403);
    });

    it('has no write operation', function (): void {
        $token = adminToken();
        expect(apiPost(AUSR_PATH, ['username' => 'x'], $token)['status'])->toBeIn([404, 405]);
        expect(apiPost(AROL_PATH, ['roleName' => 'x'], $token)['status'])->toBeIn([404, 405]);
    });
});

describe('Admin users', function (): void {

    it('lists and reads users with their role and never exposes credentials', function (): void {
        $username = 'pest_ausr_' . substr(uniqid(), -6);
        $aclToken = adminTokenWithAcl(['system/acl/users', 'system/acl/roles'], $username);

        $list = apiGet(AUSR_PATH . '?search=' . $username, $aclToken);
        expect($list['status'])->toBe(200);
        $members = ausrMembers($list);
        expect($members)->toHaveCount(1);
        $user = $members[0];
        expect($user['username'])->toBe($username)
            ->and($user['firstname'])->toBe('Pest')
            ->and($user['lastname'])->toBe('Acl')
            ->and($user['email'])->toBe($username . '@example.test')
            ->and($user['isActive'])->toBeTrue()
            ->and($user['roleName'])->toBe($username . '_role')
            ->and($user['roleId'])->toBeInt();
        foreach (AUSR_SECRET_KEYS as $key) {
            expect($user)->not->toHaveKey($key);
        }

        $get = apiGet(AUSR_PATH . "/{$user['id']}", serviceToken(['admin-users/read']));
        expect($get['status'])->toBe(200)
            ->and($get['json']['username'])->toBe($username);
        foreach (AUSR_SECRET_KEYS as $key) {
            expect($get['json'])->not->toHaveKey($key);
        }
        expect(json_encode($get['json']))->not->toContain('pest-acl-password');

        expect(ausrMembers(apiGet(AUSR_PATH . '?search=' . $username . '&isActive=false', $aclToken)))->toBe([]);
        expect(apiGet(AUSR_PATH . '/999999999', $aclToken)['status'])->toBe(404);
    });
});

describe('Admin roles', function (): void {

    it('lists group roles with their resources and user count', function (): void {
        $username = 'pest_arol_' . substr(uniqid(), -6);
        adminTokenWithAcl(['catalog/products', 'system/acl/roles'], $username);
        $token = serviceToken(['admin-roles/read']);

        $list = apiGet(AROL_PATH . '?search=' . $username, $token);
        expect($list['status'])->toBe(200);
        $members = ausrMembers($list);
        expect($members)->toHaveCount(1);
        $role = $members[0];
        expect($role['roleName'])->toBe($username . '_role')
            ->and($role['userCount'])->toBe(1)
            ->and($role['resources'])->toContain('catalog/products', 'system/acl/roles')
            ->and($role['resources'])->not->toContain('all');

        $get = apiGet(AROL_PATH . "/{$role['id']}", $token);
        expect($get['status'])->toBe(200)
            ->and($get['json']['resources'])->toBe($role['resources']);

        // The administrators role allows everything
        $all = ausrMembers(apiGet(AROL_PATH . '?search=Administrators', $token));
        expect($all)->not->toBe([])
            ->and($all[0]['resources'])->toBe(['all']);
    });

    it('hides the user assignment rows of the role table', function (): void {
        $username = 'pest_arol_user_' . substr(uniqid(), -6);
        adminTokenWithAcl(['catalog/products'], $username);
        $userId = (int) Mage::getModel('admin/user')->loadByUsername($username)->getId();
        $assignmentId = (int) Mage::getSingleton('core/resource')->getConnection('core_read')->fetchOne(
            'SELECT role_id FROM admin_role WHERE user_id = ? AND role_type = ?',
            [$userId, 'U'],
        );
        expect($assignmentId)->toBeGreaterThan(0);

        $token = serviceToken(['admin-roles/read']);
        expect(apiGet(AROL_PATH . "/{$assignmentId}", $token)['status'])->toBe(404);
        foreach (ausrMembers(apiGet(AROL_PATH . '?itemsPerPage=100', $token)) as $role) {
            expect($role['id'])->not->toBe($assignmentId);
        }
    });
});
