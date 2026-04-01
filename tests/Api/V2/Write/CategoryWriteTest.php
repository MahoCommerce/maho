<?php

/**
 * Maho
 *
 * @package    Tests
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * API v2 Category Write Tests
 *
 * End-to-end tests for category create, update, and delete via REST.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Category Permission Enforcement (REST)', function (): void {

    it('denies create without authentication', function (): void {
        $response = apiPost('/api/categories', [
            'name' => 'Test Category No Auth',
        ]);

        expect($response['status'])->toBe(401);
    });

    it('denies create with customer token (wrong role)', function (): void {
        $response = apiPost('/api/categories', [
            'name' => 'Test Category Customer',
        ], customerToken());

        expect($response['status'])->toBeForbidden();
    });

    it('denies create without correct permission', function (): void {
        $token = serviceToken(['cms-pages/write']);
        $response = apiPost('/api/categories', [
            'name' => 'Test Category No Permission',
        ], $token);

        expect($response['status'])->toBeForbidden();
    });

});

describe('Category CRUD Lifecycle (REST)', function (): void {

    it('creates a category, updates it, and deletes it', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $suffix = substr(uniqid(), -8);

        // 1. Create
        $create = apiPost('/api/categories', [
            'name' => "Pest Test Category {$suffix}",
            'isActive' => true,
            'urlKey' => "pest-test-category-{$suffix}",
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        expect($create['json'])->toHaveKey('id');
        expect($create['json']['name'])->toBe("Pest Test Category {$suffix}");

        $categoryId = $create['json']['id'];
        trackCreated('category', $categoryId);

        // 2. Read (public)
        $read = apiGet("/api/categories/{$categoryId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['name'])->toBe("Pest Test Category {$suffix}");

        // 3. Update
        $update = apiPut("/api/categories/{$categoryId}", [
            'name' => "Pest Test Category Updated {$suffix}",
        ], $token);
        expect($update['status'])->toBe(200);

        // 4. Verify update
        $verify = apiGet("/api/categories/{$categoryId}");
        expect($verify['status'])->toBe(200);
        expect($verify['json']['name'])->toBe("Pest Test Category Updated {$suffix}");

        // 5. Delete
        $delete = apiDelete("/api/categories/{$categoryId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);

        // 6. Confirm gone
        $gone = apiGet("/api/categories/{$categoryId}");
        expect($gone['status'])->toBe(404);
    });

    it('denies delete with only write permission', function (): void {
        $writeToken = serviceToken(['categories/write']);

        // Create
        $create = apiPost('/api/categories', [
            'name' => 'Pest Delete Denied Category',
            'isActive' => true,
        ], $writeToken);
        expect($create['status'])->toBeIn([200, 201]);
        $categoryId = $create['json']['id'];
        trackCreated('category', $categoryId);

        // Delete with only write = denied
        $deny = apiDelete("/api/categories/{$categoryId}", $writeToken);
        expect($deny['status'])->toBeForbidden();
    });

});

describe('Category via GraphQL (read)', function (): void {

    it('reads categories via GraphQL', function (): void {
        $query = <<<'GRAPHQL'
        {
            categoriesCategories {
                edges {
                    node {
                        id
                        _id
                        name
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('categoriesCategories');
    });

    it('reads single category by URL key via GraphQL', function (): void {
        $query = <<<'GRAPHQL'
        {
            categoryByUrlKeyCategory(urlKey: "audio") {
                _id
                name
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);
        expect($response['status'])->toBe(200);
        // May or may not exist depending on store data
    });

});
