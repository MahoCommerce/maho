<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 back-office content reads.
 *
 * Back-office callers (admin or service tokens) must be able to read back
 * what they write: disabled CMS pages by id, and the full cross-store
 * catalog via ?scope=all. Guests and customers keep the storefront view:
 * active, current-store content only, and no scope=all.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Back-office reads of disabled CMS pages', function (): void {

    it('lets the creating service token read a disabled page by id, while anonymous gets 404', function (): void {
        $token = serviceToken(['cms-pages/write']);

        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-backoffice-disabled-page',
            'title' => 'Back-Office Draft Page',
            'content' => '<p>draft</p>',
            'isActive' => false,
            'stores' => ['all'],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $pageId = $create['json']['id'];
        trackCreated('cms_page', $pageId);

        $read = apiGet("/api/rest/v2/cms-pages/{$pageId}", $token);
        expect($read['status'])->toBe(200);
        expect($read['json']['identifier'])->toBe('test-backoffice-disabled-page');
        expect($read['json']['isActive'])->toBeFalsy();

        expect(apiGet("/api/rest/v2/cms-pages/{$pageId}")['status'])->toBe(404);
    });

    it('includes a disabled page in the collection with ?scope=all for a service token', function (): void {
        $token = serviceToken(['cms-pages/write']);

        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-backoffice-scope-all-page',
            'title' => 'Back-Office Scope All Page',
            'content' => '<p>draft</p>',
            'isActive' => false,
            'stores' => ['all'],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $pageId = $create['json']['id'];
        trackCreated('cms_page', $pageId);

        $list = apiGet('/api/rest/v2/cms-pages?scope=all&pageSize=100', $token);
        expect($list['status'])->toBe(200);
        $ids = array_column(getItems($list), 'id');
        expect($ids)->toContain($pageId);

        // Without scope=all the same token still gets the storefront view.
        $default = apiGet('/api/rest/v2/cms-pages?pageSize=100', $token);
        expect($default['status'])->toBe(200);
        expect(array_column(getItems($default), 'id'))->not->toContain($pageId);
    });

    it('denies a store-restricted service token content outside its allowlist', function (): void {
        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-backoffice-restricted-page',
            'title' => 'Back-Office Restricted Page',
            'content' => '<p>draft</p>',
            'isActive' => false,
            'stores' => ['all'],
        ], serviceToken(['cms-pages/write']));

        expect($create['status'])->toBeIn([200, 201]);
        $pageId = $create['json']['id'];
        trackCreated('cms_page', $pageId);

        // All-stores content is off limits for store-restricted tokens.
        $restricted = serviceToken(['cms-pages/write'], [1]);
        expect(apiGet("/api/rest/v2/cms-pages/{$pageId}", $restricted)['status'])->toBeIn([403, 404]);
    });

    it('denies ?scope=all to anonymous and customer callers', function (): void {
        expect(apiGet('/api/rest/v2/cms-pages?scope=all')['status'])->toBe(401);
        expect(apiGet('/api/rest/v2/cms-pages?scope=all', customerToken())['status'])->toBe(403);
    });

});

describe('Back-office reads of disabled blog posts', function (): void {

    it('lets a service token read a disabled post by id, while anonymous gets 404', function (): void {
        $token = serviceToken(['blog-posts/write']);

        $create = apiPost('/api/rest/v2/blog-posts', [
            'title' => 'Back-Office Draft Post',
            'urlKey' => 'test-backoffice-disabled-post',
            'content' => '<p>draft</p>',
            'isActive' => false,
            'stores' => ['all'],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $postId = $create['json']['id'];
        trackCreated('blog_post', $postId);

        $read = apiGet("/api/rest/v2/blog-posts/{$postId}", $token);
        expect($read['status'])->toBe(200);
        expect($read['json']['urlKey'])->toBe('test-backoffice-disabled-post');

        expect(apiGet("/api/rest/v2/blog-posts/{$postId}")['status'])->toBe(404);
    });

    it('includes a disabled post in the collection with ?scope=all, hides it by default', function (): void {
        $token = serviceToken(['blog-posts/write']);

        $create = apiPost('/api/rest/v2/blog-posts', [
            'title' => 'Back-Office Scope All Post',
            'urlKey' => 'test-backoffice-scope-all-post',
            'content' => '<p>draft</p>',
            'isActive' => false,
            'stores' => ['all'],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $postId = $create['json']['id'];
        trackCreated('blog_post', $postId);

        $list = apiGet('/api/rest/v2/blog-posts?scope=all&pageSize=100', $token);
        expect($list['status'])->toBe(200);
        expect(array_column(getItems($list), 'id'))->toContain($postId);

        $anonymous = apiGet('/api/rest/v2/blog-posts?pageSize=100');
        expect($anonymous['status'])->toBe(200);
        expect(array_column(getItems($anonymous), 'id'))->not->toContain($postId);

        expect(apiGet('/api/rest/v2/blog-posts?scope=all')['status'])->toBe(401);
    });

});
