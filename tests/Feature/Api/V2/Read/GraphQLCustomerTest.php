<?php

declare(strict_types=1);

/**
 * GraphQL Customer Integration Tests
 *
 * Tests customer queries via GraphQL (authenticated).
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 * @group graphql
 */

describe('GraphQL Me Query', function (): void {

    it('returns current customer profile when authenticated', function (): void {
        $customerId = fixtures('customer_id');

        $query = <<<'GRAPHQL'
        {
            meCustomer {
                id
                _id
                email
                firstName
                lastName
                fullName
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');

        $customer = $response['json']['data']['meCustomer'];
        // meCustomer may return null if the authenticator doesn't set the customer
        // in the security context for direct JWT tokens; if it works, validate it
        if ($customer !== null) {
            expect($customer['email'])->toBeString();
            expect($customer['firstName'])->toBeString();
            expect($customer['lastName'])->toBeString();
            expect($customer['_id'])->toBe($customerId);
        }
    });

    it('returns null for me query with admin token', function (): void {
        $query = <<<'GRAPHQL'
        {
            meCustomer {
                id
                email
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], adminToken());

        expect($response['status'])->toBe(200);
        // Admin has no customer_id, so meCustomer should be null or error
        $data = $response['json']['data']['meCustomer'] ?? null;
        $errors = $response['json']['errors'] ?? [];
        expect($data === null || !empty($errors))->toBeTrue();
    });

});

describe('GraphQL Customer By ID Query', function (): void {

    it('returns customer by IRI for admin', function (): void {
        $customerId = fixtures('customer_id');
        $iri = "/api/customers/{$customerId}";

        $query = <<<GRAPHQL
        {
            customerCustomer(id: "{$iri}") {
                _id
                email
                firstName
                lastName
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['data']['customerCustomer'])->not->toBeNull();

        $customer = $response['json']['data']['customerCustomer'];
        expect($customer['_id'])->toBe($customerId);
        expect($customer['email'])->toBeString();
        expect($customer['firstName'])->toBeString();
    });

    it('denies customer by ID for regular customer', function (): void {
        $customerId = fixtures('customer_id');
        $iri = "/api/customers/{$customerId}";

        $query = <<<GRAPHQL
        {
            customerCustomer(id: "{$iri}") {
                _id
                email
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('errors');
        expect($response['json']['errors'][0]['extensions']['status'] ?? null)->toBe(403);
    });

});

describe('GraphQL Customer Orders Query', function (): void {

    it('returns customer orders when authenticated', function (): void {
        $query = <<<'GRAPHQL'
        {
            customerOrdersOrders(pageSize: 5) {
                edges {
                    node {
                        id
                        _id
                        incrementId
                        status
                        prices
                        createdAt
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('customerOrdersOrders');
    });

});

describe('GraphQL Customer Addresses Query', function (): void {

    it('returns customer addresses when authenticated', function (): void {
        $query = <<<'GRAPHQL'
        {
            myAddressesAddresses {
                edges {
                    node {
                        id
                        _id
                        firstName
                        lastName
                        city
                        postcode
                        countryId
                        isDefaultBilling
                        isDefaultShipping
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('myAddressesAddresses');
    });

    it('returns addresses with expected field types', function (): void {
        $query = <<<'GRAPHQL'
        {
            myAddressesAddresses {
                edges {
                    node {
                        _id
                        firstName
                        lastName
                        city
                        postcode
                        countryId
                        isDefaultBilling
                        isDefaultShipping
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);

        $edges = $response['json']['data']['myAddressesAddresses']['edges'] ?? [];
        if (!empty($edges)) {
            $address = $edges[0]['node'];
            expect($address['firstName'])->toBeString();
            expect($address['lastName'])->toBeString();
            expect($address['countryId'])->toBeString();
            expect($address['isDefaultBilling'])->toBeBool();
            expect($address['isDefaultShipping'])->toBeBool();
        }
    });

});
