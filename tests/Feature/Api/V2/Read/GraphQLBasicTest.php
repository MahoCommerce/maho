<?php

declare(strict_types=1);

/**
 * GraphQL Basic Integration Tests
 *
 * Tests that the GraphQL endpoint works, handles auth, and supports introspection.
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 * @group graphql
 */

describe('GraphQL Endpoint - Authentication', function () {

    it('allows unauthenticated access to public queries', function () {
        $response = gqlQuery('{ __typename }');

        expect($response['status'])->toBeSuccessful();
    });

    it('denies unauthenticated access to protected operations', function () {
        $query = '{ myWishlistWishlistItems(first: 1) { edges { node { _id } } } }';
        $response = gqlQuery($query);

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('errors');
        // Unauthenticated → 401 or 403 depending on API Platform's security voter
        expect($response['json']['errors'][0]['extensions']['status'] ?? null)->toBeIn([401, 403]);
    });

    it('rejects GraphQL requests with invalid token', function () {
        $response = gqlQuery('{ __typename }', [], invalidToken());

        expect($response['status'])->toBeUnauthorized();
    });

    it('rejects GraphQL requests with expired token', function () {
        $response = gqlQuery('{ __typename }', [], expiredToken());

        expect($response['status'])->toBeUnauthorized();
    });

    it('accepts GraphQL requests with valid customer token', function () {
        $response = gqlQuery('{ __typename }', [], customerToken());

        expect($response['status'])->toBeSuccessful();
    });

    it('accepts GraphQL requests with valid admin token', function () {
        $response = gqlQuery('{ __typename }', [], adminToken());

        expect($response['status'])->toBeSuccessful();
    });

});

describe('GraphQL Endpoint - Introspection', function () {

    it('supports introspection queries', function () {
        $query = <<<'GRAPHQL'
        {
            __schema {
                queryType { name }
                mutationType { name }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('__schema');
        expect($response['json']['data']['__schema'])->toHaveKey('queryType');
        expect($response['json']['data']['__schema'])->toHaveKey('mutationType');
    });

    it('returns product type in schema', function () {
        $query = <<<'GRAPHQL'
        {
            __type(name: "Product") {
                name
                fields { name }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['data']['__type'])->not->toBeNull();
        expect($response['json']['data']['__type']['name'])->toBe('Product');

        $fieldNames = array_column($response['json']['data']['__type']['fields'], 'name');
        expect($fieldNames)->toContain('sku');
        expect($fieldNames)->toContain('name');
        expect($fieldNames)->toContain('price');
    });

    it('returns cart type with prices field in schema', function () {
        $query = <<<'GRAPHQL'
        {
            __type(name: "Cart") {
                name
                fields { name }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['data']['__type'])->not->toBeNull();

        $fieldNames = array_column($response['json']['data']['__type']['fields'], 'name');
        expect($fieldNames)->toContain('prices');
        expect($fieldNames)->toContain('items');
        expect($fieldNames)->toContain('maskedId');
    });

});

describe('GraphQL Endpoint - Error Handling', function () {

    it('returns errors for invalid query syntax', function () {
        $response = gqlQuery('{ invalid query syntax !!!', [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('errors');
    });

    it('returns errors for non-existent field', function () {
        $response = gqlQuery('{ nonExistentField }', [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('errors');
    });

});
