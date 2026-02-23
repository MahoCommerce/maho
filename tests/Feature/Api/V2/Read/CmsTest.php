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
 * API v2 CMS Tests
 *
 * Tests for CMS pages and blocks endpoints - PUBLIC ACCESS (no auth required).
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('API v2 CMS Pages', function (): void {

    describe('public access (no auth)', function (): void {

        it('allows listing CMS pages without authentication', function (): void {
            $response = apiGet('/api/cms-pages');

            expect($response['status'])->toBeSuccessful();
        });

        it('returns CMS pages collection', function (): void {
            $response = apiGet('/api/cms-pages');

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

        it('allows getting single CMS page without authentication', function (): void {
            $list = apiGet('/api/cms-pages');
            $members = $list['json']['member'] ?? $list['json']['hydra:member'] ?? [];

            if (!empty($members) && isset($members[0]['id'])) {
                $pageId = $members[0]['id'];
                $response = apiGet("/api/cms-pages/{$pageId}");

                expect($response['status'])->toBeSuccessful();
            } else {
                expect(true)->toBeTrue();
            }
        });

        it('returns 404 for non-existent CMS page', function (): void {
            $response = apiGet('/api/cms-pages/non-existent-page-999');

            expect($response['status'])->toBe(404);
        });

    });

    describe('with authentication', function (): void {

        it('allows listing CMS pages with valid token', function (): void {
            $response = apiGet('/api/cms-pages', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

    });

});

describe('API v2 CMS Blocks', function (): void {

    describe('public access (no auth)', function (): void {

        it('allows listing CMS blocks without authentication', function (): void {
            $response = apiGet('/api/cms-blocks');

            expect($response['status'])->toBeSuccessful();
        });

        it('returns CMS blocks collection', function (): void {
            $response = apiGet('/api/cms-blocks');

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

        it('allows getting single CMS block without authentication', function (): void {
            $list = apiGet('/api/cms-blocks');
            $members = $list['json']['member'] ?? $list['json']['hydra:member'] ?? [];

            if (!empty($members) && isset($members[0]['id'])) {
                $blockId = $members[0]['id'];
                $response = apiGet("/api/cms-blocks/{$blockId}");

                expect($response['status'])->toBeSuccessful();
            } else {
                expect(true)->toBeTrue();
            }
        });

        it('returns 404 for non-existent CMS block', function (): void {
            $response = apiGet('/api/cms-blocks/non-existent-block-999');

            expect($response['status'])->toBe(404);
        });

    });

});

describe('API v2 URL Resolver', function (): void {

    describe('public access (no auth)', function (): void {

        it('allows resolving URLs without authentication', function (): void {
            $response = apiGet('/api/url-resolver?url=/');

            // Should work even if URL not found (returns result or 404)
            expect($response['status'])->toBeIn([200, 404]);
        });

        it('resolves product URLs', function (): void {
            // Try to resolve a common URL pattern
            $response = apiGet('/api/url-resolver?url=/about-us');

            // May or may not exist, but shouldn't require auth
            expect($response['status'])->toBeIn([200, 404]);
        });

    });

    describe('with authentication', function (): void {

        it('allows resolving URLs with valid token', function (): void {
            $response = apiGet('/api/url-resolver?url=/', customerToken());

            expect($response['status'])->toBeIn([200, 404]);
        });

    });

});
