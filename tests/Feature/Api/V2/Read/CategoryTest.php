<?php

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

        it('allows listing categories without authentication', function (): void {
            $response = apiGet('/api/categories');

            expect($response['status'])->toBeSuccessful();
        });

        it('returns category collection in JSON-LD format', function (): void {
            $response = apiGet('/api/categories');

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
            // Should have collection format with 'member' or 'hydra:member'
            $hasMember = isset($response['json']['member']) || isset($response['json']['hydra:member']);
            expect($hasMember)->toBeTrue('Response should have "member" or "hydra:member" key');
        });

        it('allows getting single category without authentication', function (): void {
            // Get the list first to find a valid ID
            $list = apiGet('/api/categories');
            $members = $list['json']['member'] ?? $list['json']['hydra:member'] ?? [];

            if (!empty($members) && isset($members[0]['id'])) {
                $categoryId = $members[0]['id'];
                $response = apiGet("/api/categories/{$categoryId}");

                expect($response['status'])->toBeSuccessful();
            } else {
                // Skip if no categories exist
                expect(true)->toBeTrue();
            }
        });

        it('returns 404 for non-existent category', function (): void {
            $response = apiGet('/api/categories/999999');

            expect($response['status'])->toBe(404);
        });

    });

    describe('with authentication', function (): void {

        it('allows listing categories with valid token', function (): void {
            $response = apiGet('/api/categories', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

        it('allows listing categories with admin token', function (): void {
            $response = apiGet('/api/categories', adminToken());

            expect($response['status'])->toBeSuccessful();
        });

    });

    describe('response format', function (): void {

        it('includes expected category fields', function (): void {
            $response = apiGet('/api/categories');
            $members = $response['json']['member'] ?? $response['json']['hydra:member'] ?? [];

            if (!empty($members) && isset($members[0])) {
                $category = $members[0];

                // Categories should have these basic fields
                expect($category)->toHaveKey('id');
                expect($category)->toHaveKey('name');
            } else {
                expect(true)->toBeTrue(); // Skip if no data
            }
        });

    });

});
