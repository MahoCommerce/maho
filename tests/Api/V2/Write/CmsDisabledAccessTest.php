<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 CMS disabled-content access control.
 *
 * Regression tests: disabled (is_active = 0) CMS pages and blocks are hidden
 * from collection/identifier lookups but must ALSO be unreachable through the
 * public GET /{resource}/{id} numeric route. Enumerating ids must not leak
 * draft/disabled content.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('CMS disabled page is not readable by numeric id', function (): void {

    it('returns 404 for a disabled page fetched by id (unauthenticated)', function (): void {
        $writeToken = serviceToken(['cms-pages/write']);

        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-disabled-page-access',
            'title' => 'Disabled Page',
            'content' => '<p>secret draft</p>',
            'isActive' => false,
            'stores' => ['all'],
        ], $writeToken);

        expect($create['status'])->toBeIn([200, 201]);
        $pageId = $create['json']['id'];
        trackCreated('cms_page', $pageId);

        // The disabled page must not be readable by enumerating its numeric id.
        expect(apiGet("/api/rest/v2/cms-pages/{$pageId}")['status'])->toBe(404);
        // ...nor with a logged-in customer (still not active).
        expect(apiGet("/api/rest/v2/cms-pages/{$pageId}", customerToken())['status'])->toBe(404);
    });

    it('still serves an enabled page by id (positive control)', function (): void {
        $writeToken = serviceToken(['cms-pages/write']);

        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-enabled-page-access',
            'title' => 'Enabled Page',
            'content' => '<p>public</p>',
            'isActive' => true,
            'stores' => ['all'],
        ], $writeToken);

        expect($create['status'])->toBeIn([200, 201]);
        $pageId = $create['json']['id'];
        trackCreated('cms_page', $pageId);

        expect(apiGet("/api/rest/v2/cms-pages/{$pageId}")['status'])->toBe(200);
    });

});

describe('CMS disabled block is not readable by numeric id', function (): void {

    it('returns 404 for a disabled block fetched by id (unauthenticated)', function (): void {
        $writeToken = serviceToken(['cms-blocks/write']);

        $create = apiPost('/api/rest/v2/cms-blocks', [
            'identifier' => 'test-disabled-block-access',
            'title' => 'Disabled Block',
            'content' => '<p>secret draft block</p>',
            'isActive' => false,
            'stores' => ['all'],
        ], $writeToken);

        expect($create['status'])->toBeIn([200, 201]);
        $blockId = $create['json']['id'];
        trackCreated('cms_block', $blockId);

        expect(apiGet("/api/rest/v2/cms-blocks/{$blockId}")['status'])->toBe(404);
    });

    it('still serves an enabled block by id (positive control)', function (): void {
        $writeToken = serviceToken(['cms-blocks/write']);

        $create = apiPost('/api/rest/v2/cms-blocks', [
            'identifier' => 'test-enabled-block-access',
            'title' => 'Enabled Block',
            'content' => '<p>public block</p>',
            'isActive' => true,
            'stores' => ['all'],
        ], $writeToken);

        expect($create['status'])->toBeIn([200, 201]);
        $blockId = $create['json']['id'];
        trackCreated('cms_block', $blockId);

        expect(apiGet("/api/rest/v2/cms-blocks/{$blockId}")['status'])->toBe(200);
    });

});
