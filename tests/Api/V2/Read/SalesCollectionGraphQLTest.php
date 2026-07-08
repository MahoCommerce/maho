<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 GraphQL list-all collections for Shipment and CreditMemo.
 *
 * These unscoped GraphQL collection queries (`shipments`,
 * `creditMemos`) previously had no working code path: the provider
 * routed every collection into the per-order branch with orderId 0 and threw
 * ("Order ID is required" / "Order not found"), so admins could never list all
 * shipments or credit memos. These tests lock in the admin list-all behaviour.
 *
 * READ-ONLY (queries only), safe for a synced database.
 *
 * @group read
 */

describe('GraphQL shipments list-all', function (): void {

    it('lets an admin list all shipments without an order id', function (): void {
        $query = <<<'GRAPHQL'
        query {
            shipments {
                edges { node { id } }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], adminToken());

        // Used to come back with a RuntimeException ("Order ID is required").
        expect($response['json'])->not->toHaveKey('errors');
        expect($response['json']['data']['shipments'] ?? null)->not->toBeNull();
        expect($response['json']['data']['shipments'])->toHaveKey('edges');
        expect($response['json']['data']['shipments']['edges'])->toBeArray();
    });

    it('denies the shipments list-all to a customer', function (): void {
        $query = <<<'GRAPHQL'
        query {
            shipments {
                edges { node { id } }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['json'])->toHaveKey('errors');
    });

});

describe('GraphQL credit memos list-all', function (): void {

    it('lets an admin list all credit memos without an order id', function (): void {
        $query = <<<'GRAPHQL'
        query {
            creditMemos {
                edges { node { id } }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], adminToken());

        // Used to come back with a NotFoundHttpException ("Order not found").
        expect($response['json'])->not->toHaveKey('errors');
        expect($response['json']['data']['creditMemos'] ?? null)->not->toBeNull();
        expect($response['json']['data']['creditMemos'])->toHaveKey('edges');
        expect($response['json']['data']['creditMemos']['edges'])->toBeArray();
    });

    it('denies the credit memos list-all to a customer', function (): void {
        $query = <<<'GRAPHQL'
        query {
            creditMemos {
                edges { node { id } }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['json'])->toHaveKey('errors');
    });

});
