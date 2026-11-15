<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Category Tests
 *
 * Tests for category endpoints - PUBLIC ACCESS (no auth required).
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('API v2 Categories', function (): void {

    describe('public access (no auth)', function (): void {

        it('returns category collection in JSON-LD format', function (): void {
            $response = apiGet('/api/rest/v2/categories');

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
            // Should have collection format with 'member' or 'hydra:member'
            $hasMember = isset($response['json']['member']) || isset($response['json']['hydra:member']);
            expect($hasMember)->toBeTrue('Response should have "member" or "hydra:member" key');
        });

        it('allows getting single category without authentication', function (): void {
            // Get the list first to find a valid ID
            $list = apiGet('/api/rest/v2/categories');
            $members = $list['json']['member'] ?? $list['json']['hydra:member'] ?? [];
            expect($members)->not->toBeEmpty();

            $response = apiGet("/api/rest/v2/categories/{$members[0]['id']}");

            expect($response['status'])->toBeSuccessful();
        });

        it('returns 404 for non-existent category', function (): void {
            $response = apiGet('/api/rest/v2/categories/999999');

            expect($response['status'])->toBe(404);
        });

    });

    describe('with authentication', function (): void {

        it('allows listing categories with admin token', function (): void {
            $response = apiGet('/api/rest/v2/categories', adminToken());

            expect($response['status'])->toBeSuccessful();
        });

    });

    describe('response format', function (): void {

        it('includes expected category fields', function (): void {
            $response = apiGet('/api/rest/v2/categories');
            $members = $response['json']['member'] ?? $response['json']['hydra:member'] ?? [];

            expect($members)->not->toBeEmpty();
            expect($members[0])->toHaveKey('id');
            expect($members[0])->toHaveKey('name');
        });

    });

});
