<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
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

        it('returns CMS pages collection', function (): void {
            $response = apiGet('/api/rest/v2/cms-pages');

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

        it('allows getting single CMS page without authentication', function (): void {
            $list = apiGet('/api/rest/v2/cms-pages');
            $members = $list['json']['member'] ?? $list['json']['hydra:member'] ?? [];
            expect($members)->not->toBeEmpty();

            $response = apiGet("/api/rest/v2/cms-pages/{$members[0]['id']}");

            expect($response['status'])->toBeSuccessful();
        });

        it('returns 404 for non-existent CMS page', function (): void {
            $response = apiGet('/api/rest/v2/cms-pages/non-existent-page-999');

            expect($response['status'])->toBe(404);
        });

    });

});

describe('API v2 CMS Blocks', function (): void {

    describe('public access (no auth)', function (): void {

        it('returns CMS blocks collection', function (): void {
            $response = apiGet('/api/rest/v2/cms-blocks');

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

        it('allows getting single CMS block without authentication', function (): void {
            $list = apiGet('/api/rest/v2/cms-blocks');
            $members = $list['json']['member'] ?? $list['json']['hydra:member'] ?? [];
            expect($members)->not->toBeEmpty();

            $response = apiGet("/api/rest/v2/cms-blocks/{$members[0]['id']}");

            expect($response['status'])->toBeSuccessful();
        });

        it('returns 404 for non-existent CMS block', function (): void {
            $response = apiGet('/api/rest/v2/cms-blocks/non-existent-block-999');

            expect($response['status'])->toBe(404);
        });

    });

});

describe('API v2 URL Resolver', function (): void {

    it('resolves the home page without authentication', function (): void {
        $response = apiGet('/api/rest/v2/url-resolver?path=/');

        expect($response['status'])->toBe(200);
        $items = getItems($response);
        expect($items)->not->toBeEmpty();
        expect($items[0]['type'] ?? null)->toBe('cms_page');
        expect($items[0]['identifier'] ?? null)->toBe('home');
    });

    it('resolves a product URL key', function (): void {
        $sku = fixtures('configurable_sku');
        if (!$sku) {
            $this->markTestSkipped('No configurable_sku configured in fixtures');
        }
        $product = \Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);

        $response = apiGet('/api/rest/v2/url-resolver?path=' . urlencode((string) $product->getUrlKey()));

        expect($response['status'])->toBe(200);
        $items = getItems($response);
        expect($items)->not->toBeEmpty();
        expect($items[0]['type'] ?? null)->toBe('product');
        expect($items[0]['id'] ?? null)->toBe((int) $product->getId());
    });

});
