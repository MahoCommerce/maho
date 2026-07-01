<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Auth Flow Tests
 *
 * Tests the OAuth2 client_credentials and api_user grant flows end-to-end.
 * Uses the test API user (user_id=1, client_id=maho_5293a399b1369914b84a9958466e026e).
 *
 * The test API user has "all" permissions and a known client_secret set
 * for testing purposes ("pest_test_secret_12345").
 *
 * @group write
 */

// Test API user credentials. The matching user is seeded in beforeAll() below
// (and torn down in afterAll()) so the suite is self-contained, there is no
// fixture file or install script that creates it.
const TEST_CLIENT_ID = 'maho_5293a399b1369914b84a9958466e026e';
const TEST_CLIENT_SECRET = 'pest_test_secret_12345';
const TEST_API_USERNAME = 'pest_auth_flow_user';
const TEST_API_ROLE_NAME = 'Pest Auth Flow Role';

beforeAll(function (): void {
    Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();

    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $userTable = $resource->getTableName('api/user');
    $roleTable = $resource->getTableName('api/role');
    $ruleTable = $resource->getTableName('api/rule');

    // Wipe any leftovers from a prior aborted run before re-seeding.
    $existingUser = Mage::getModel('api/user')->loadByUsername(TEST_API_USERNAME);
    if ($existingUser->getId()) {
        $write->delete($roleTable, ['user_id = ?' => $existingUser->getId(), 'role_type = ?' => 'U']);
        $existingUser->delete();
    }
    $existingRoleId = $write->fetchOne(
        $write->select()->from($roleTable, 'role_id')
            ->where('role_name = ?', TEST_API_ROLE_NAME)
            ->where('role_type = ?', 'G'),
    );
    if ($existingRoleId) {
        $write->delete($ruleTable, ['role_id = ?' => $existingRoleId, 'role_type = ?' => 'G']);
        $write->delete($roleTable, ['role_id = ?' => $existingRoleId, 'role_type = ?' => 'G']);
    }

    // Group role with 'all' permission.
    $write->insert($roleTable, [
        'parent_id'  => 0,
        'tree_level' => 1,
        'sort_order' => 0,
        'role_type'  => 'G',
        'user_id'    => 0,
        'role_name'  => TEST_API_ROLE_NAME,
    ]);
    $roleId = (int) $write->lastInsertId();
    $write->insert($ruleTable, [
        'role_id'        => $roleId,
        'role_type'      => 'G',
        'resource_id'    => 'all',
        'api_permission' => 'allow',
    ]);

    // API user.
    $user = Mage::getModel('api/user');
    $user->setUsername(TEST_API_USERNAME)
        ->setFirstname('Pest')
        ->setLastname('Test')
        ->setEmail('pest-auth-flow@test.local')
        ->setIsActive(1)
        ->setApiKey(bin2hex(random_bytes(16)))
        ->save();

    // client_id / client_secret aren't on the model, write directly.
    $write->update($userTable, [
        'client_id'     => TEST_CLIENT_ID,
        'client_secret' => password_hash(TEST_CLIENT_SECRET, PASSWORD_BCRYPT),
    ], ['user_id = ?' => (int) $user->getId()]);

    // Bind user to the role.
    $write->insert($roleTable, [
        'parent_id'  => $roleId,
        'tree_level' => 2,
        'sort_order' => 0,
        'role_type'  => 'U',
        'user_id'    => (int) $user->getId(),
        'role_name'  => TEST_API_USERNAME,
    ]);
});

afterAll(function (): void {
    Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();

    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $roleTable = $resource->getTableName('api/role');
    $ruleTable = $resource->getTableName('api/rule');

    $user = Mage::getModel('api/user')->loadByUsername(TEST_API_USERNAME);
    if ($user->getId()) {
        $write->delete($roleTable, ['user_id = ?' => $user->getId(), 'role_type = ?' => 'U']);
        $user->delete();
    }

    $roleId = $write->fetchOne(
        $write->select()->from($roleTable, 'role_id')
            ->where('role_name = ?', TEST_API_ROLE_NAME)
            ->where('role_type = ?', 'G'),
    );
    if ($roleId) {
        $write->delete($ruleTable, ['role_id = ?' => $roleId, 'role_type = ?' => 'G']);
        $write->delete($roleTable, ['role_id = ?' => $roleId, 'role_type = ?' => 'G']);
    }
});

describe('OAuth2 Client Credentials Flow', function (): void {

    it('rejects invalid client_id', function (): void {
        $response = apiPost('/api/rest/v2/auth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => 'nonexistent_key_12345',
            'client_secret' => 'doesntmatter',
        ]);

        expect($response['status'])->toBe(401);
        expect($response['json']['error'] ?? '')->toBe('invalid_client');
    });

    it('rejects invalid client_secret', function (): void {
        $response = apiPost('/api/rest/v2/auth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => TEST_CLIENT_ID,
            'client_secret' => 'wrong_secret_12345',
        ]);

        expect($response['status'])->toBe(401);
        expect($response['json']['error'] ?? '')->toBe('invalid_client');
    });

    it('rejects missing client_id', function (): void {
        $response = apiPost('/api/rest/v2/auth/token', [
            'grant_type' => 'client_credentials',
            'client_secret' => TEST_CLIENT_SECRET,
        ]);

        expect($response['status'])->toBe(400);
    });

    it('returns token for valid credentials', function (): void {
        $response = apiPost('/api/rest/v2/auth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => TEST_CLIENT_ID,
            'client_secret' => TEST_CLIENT_SECRET,
        ]);

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('token');
        expect($response['json'])->toHaveKey('token_type');
        expect($response['json']['token_type'])->toBe('Bearer');
        expect($response['json'])->toHaveKey('expires_in');
        expect($response['json'])->toHaveKey('permissions');
        expect($response['json']['permissions'])->toContain('all');
    });

    it('returned token works for authorized endpoints', function (): void {
        // Get a real token
        $authResponse = apiPost('/api/rest/v2/auth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => TEST_CLIENT_ID,
            'client_secret' => TEST_CLIENT_SECRET,
        ]);
        expect($authResponse['status'])->toBe(200);
        $token = $authResponse['json']['token'];

        // This user has "all" permissions, create a CMS page
        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'pest-auth-flow-test-' . substr(uniqid(), -8),
            'title' => 'Auth Flow Test Page',
            'content' => '<p>Test</p>',
            'isActive' => true,
            'stores' => ['all'],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $pageId = $create['json']['id'];
        trackCreated('cms_page', $pageId);

        // Cleanup
        $delete = apiDelete("/api/rest/v2/cms-pages/{$pageId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);
    });

});

describe('Customer Auth Grant', function (): void {

    it('rejects missing email', function (): void {
        $response = apiPost('/api/rest/v2/auth/token', [
            'grant_type' => 'customer',
            'password' => 'test123',
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json']['error'] ?? '')->toBe('invalid_request');
    });

    it('rejects invalid email format', function (): void {
        $response = apiPost('/api/rest/v2/auth/token', [
            'grant_type' => 'customer',
            'email' => 'not-an-email',
            'password' => 'test123',
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json']['error'] ?? '')->toBe('invalid_request');
    });

    it('rejects non-existent customer', function (): void {
        $response = apiPost('/api/rest/v2/auth/token', [
            'grant_type' => 'customer',
            'email' => 'nonexistent@example.com',
            'password' => 'test123',
        ]);

        expect($response['status'])->toBe(401);
        expect($response['json']['error'] ?? '')->toBe('invalid_credentials');
    });

    it('defaults to customer grant without grant_type', function (): void {
        // Without grant_type, defaults to 'customer' which requires email
        $response = apiPost('/api/rest/v2/auth/token', [
            'email' => 'nonexistent@example.com',
            'password' => 'test123',
        ]);

        // Should be 401 (invalid credentials), not 400 (invalid grant_type)
        expect($response['status'])->toBe(401);
    });

});

describe('Unsupported Grant Types', function (): void {

    it('rejects unsupported grant_type', function (): void {
        $response = apiPost('/api/rest/v2/auth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => TEST_CLIENT_ID,
            'client_secret' => TEST_CLIENT_SECRET,
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json']['error'] ?? '')->toBe('unsupported_grant_type');
    });

});
