<?php

declare(strict_types=1);

/**
 * API v2 Category Write Tests
 *
 * End-to-end tests for category create, update, and delete via REST.
 *
 * Known issue: Category DELETE returns 422 "Cannot complete this operation
 * from non-admin area." — same Maho catalog model limitation as products.
 *
 * @group write
 */

afterAll(function () {
    cleanupTestData();
});

describe('Category Permission Enforcement (REST)', function () {

    it('denies create without authentication', function () {
        $response = apiPost('/api/categories', [
            'name' => 'Test Category No Auth',
        ]);

        expect($response['status'])->toBe(401);
    });

    it('denies create with customer token (wrong role)', function () {
        $response = apiPost('/api/categories', [
            'name' => 'Test Category Customer',
        ], customerToken());

        expect($response['status'])->toBeForbidden();
    });

    it('denies create without correct permission', function () {
        $token = serviceToken(['cms-pages/write']);
        $response = apiPost('/api/categories', [
            'name' => 'Test Category No Permission',
        ], $token);

        expect($response['status'])->toBeForbidden();
    });

});

describe('Category Create & Update Lifecycle (REST)', function () {

    it('creates a category and reads it back', function () {
        $writeToken = serviceToken(['categories/write', 'categories/delete']);
        $suffix = substr(uniqid(), -8);

        // 1. Create
        $create = apiPost('/api/categories', [
            'name' => "Pest Test Category {$suffix}",
            'isActive' => true,
            'urlKey' => "pest-test-category-{$suffix}",
        ], $writeToken);

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
        ], $writeToken);
        expect($update['status'])->toBe(200);

        // 4. Verify update
        $verify = apiGet("/api/categories/{$categoryId}");
        expect($verify['status'])->toBe(200);
        expect($verify['json']['name'])->toBe("Pest Test Category Updated {$suffix}");
    });

    it('denies delete with only write permission', function () {
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

describe('Category Delete (known bug: 422 non-admin area)', function () {

    /**
     * Known bug: Category DELETE returns 422 with
     * "Cannot complete this operation from non-admin area."
     * Same Maho catalog model limitation as product delete.
     */
    it('category delete returns 422 non-admin area (known bug)', function () {
        $token = serviceToken(['categories/write', 'categories/delete']);

        // Create a throwaway category
        $create = apiPost('/api/categories', [
            'name' => 'Pest Delete Bug Test',
            'isActive' => true,
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $categoryId = $create['json']['id'];
        trackCreated('category', $categoryId);

        // Delete returns 422 (known bug)
        $delete = apiDelete("/api/categories/{$categoryId}", $token);
        expect($delete['status'])->toBe(422);
        expect($delete['json']['message'] ?? '')->toContain('non-admin area');
    });

});

describe('Category via GraphQL (read)', function () {

    it('reads categories via GraphQL', function () {
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

    it('reads single category by URL key via GraphQL', function () {
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
