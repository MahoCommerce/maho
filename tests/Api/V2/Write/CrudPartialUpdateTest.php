<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * A PUT on a generic CRUD resource writes only the fields in the request body.
 *
 * @group write
 */

afterAll(function (): void {
    foreach ($GLOBALS['_test_partial_update_blog_categories'] ?? [] as $id) {
        try {
            Mage::getSingleton('core/resource')->getConnection('core_write')
                ->delete('blog_category_entity', ['entity_id = ?' => $id]);
        } catch (\Throwable) {
        }
    }
    cleanupTestData();
});

function partialUpdateStoreId(): int
{
    return (int) Mage::app()->getDefaultStoreView()->getId();
}

describe('Partial PUT on generic CRUD resources', function (): void {

    it('keeps the omitted fields of a blog post', function (): void {
        $token = serviceToken(['blog-posts/write', 'blog-posts/delete']);
        $suffix = uniqid();
        $storeId = partialUpdateStoreId();

        $create = apiPost('/api/rest/v2/blog-posts', [
            'title' => 'Partial Post',
            'urlKey' => "test-pest-partial-post-{$suffix}",
            'content' => '<p>Partial post content</p>',
            'isActive' => true,
            'stores' => [$storeId],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $postId = (int) $create['json']['id'];
        trackCreated('blog_post', $postId);

        $update = apiPut("/api/rest/v2/blog-posts/{$postId}", ['categoryIds' => []], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['title'])->toBe('Partial Post');
        expect($update['json']['urlKey'])->toBe("test-pest-partial-post-{$suffix}");
        expect($update['json']['content'])->toContain('Partial post content');
        expect($update['json']['isActive'])->toBeTrue();
        expect($update['json']['stores'])->toBe([$storeId]);

        $full = apiPut("/api/rest/v2/blog-posts/{$postId}", [
            'title' => 'Partial Post Changed',
            'urlKey' => "test-pest-partial-post-changed-{$suffix}",
            'content' => '<p>Changed post content</p>',
            'isActive' => false,
            'stores' => ['all'],
        ], $token);
        expect($full['status'])->toBe(200);
        expect($full['json']['title'])->toBe('Partial Post Changed');
        expect($full['json']['urlKey'])->toBe("test-pest-partial-post-changed-{$suffix}");
        expect($full['json']['content'])->toContain('Changed post content');
        expect($full['json']['isActive'])->toBeFalse();
        expect($full['json']['stores'])->toBe([0]);

        expect(apiDelete("/api/rest/v2/blog-posts/{$postId}", $token)['status'])->toBeIn([200, 204]);
    });

    it('keeps the omitted fields of a CMS page', function (): void {
        $token = serviceToken(['cms-pages/write', 'cms-pages/delete']);
        $suffix = uniqid();
        $storeId = partialUpdateStoreId();

        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => "test-pest-partial-page-{$suffix}",
            'title' => 'Partial Page',
            'content' => '<p>Partial page content</p>',
            'isActive' => true,
            'stores' => [$storeId],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $pageId = (int) $create['json']['id'];
        trackCreated('cms_page', $pageId);

        $update = apiPut("/api/rest/v2/cms-pages/{$pageId}", ['contentHeading' => 'Heading'], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['contentHeading'])->toBe('Heading');
        expect($update['json']['identifier'])->toBe("test-pest-partial-page-{$suffix}");
        expect($update['json']['title'])->toBe('Partial Page');
        expect($update['json']['content'])->toContain('Partial page content');
        expect($update['json']['isActive'])->toBeTrue();
        expect($update['json']['stores'])->toBe([$storeId]);

        $full = apiPut("/api/rest/v2/cms-pages/{$pageId}", [
            'identifier' => "test-pest-partial-page-changed-{$suffix}",
            'title' => 'Partial Page Changed',
            'content' => '<p>Changed page content</p>',
            'isActive' => false,
            'stores' => ['all'],
        ], $token);
        expect($full['status'])->toBe(200);
        expect($full['json']['identifier'])->toBe("test-pest-partial-page-changed-{$suffix}");
        expect($full['json']['title'])->toBe('Partial Page Changed');
        expect($full['json']['content'])->toContain('Changed page content');
        expect($full['json']['isActive'])->toBeFalse();
        expect($full['json']['stores'])->toBe([0]);

        expect(apiDelete("/api/rest/v2/cms-pages/{$pageId}", $token)['status'])->toBeIn([200, 204]);
    });

    it('keeps the omitted fields of a CMS block', function (): void {
        $token = serviceToken(['cms-blocks/write', 'cms-blocks/delete']);
        $suffix = uniqid();
        $storeId = partialUpdateStoreId();

        $create = apiPost('/api/rest/v2/cms-blocks', [
            'identifier' => "test-pest-partial-block-{$suffix}",
            'title' => 'Partial Block',
            'content' => '<p>Partial block content</p>',
            'isActive' => true,
            'stores' => [$storeId],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $blockId = (int) $create['json']['id'];
        trackCreated('cms_block', $blockId);

        $update = apiPut("/api/rest/v2/cms-blocks/{$blockId}", ['content' => '<p>Only content</p>'], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['content'])->toContain('Only content');
        expect($update['json']['identifier'])->toBe("test-pest-partial-block-{$suffix}");
        expect($update['json']['title'])->toBe('Partial Block');
        expect($update['json']['isActive'])->toBeTrue();
        expect($update['json']['stores'])->toBe([$storeId]);

        $full = apiPut("/api/rest/v2/cms-blocks/{$blockId}", [
            'identifier' => "test-pest-partial-block-changed-{$suffix}",
            'title' => 'Partial Block Changed',
            'content' => '<p>Changed block content</p>',
            'isActive' => false,
            'stores' => ['all'],
        ], $token);
        expect($full['status'])->toBe(200);
        expect($full['json']['identifier'])->toBe("test-pest-partial-block-changed-{$suffix}");
        expect($full['json']['title'])->toBe('Partial Block Changed');
        expect($full['json']['content'])->toContain('Changed block content');
        expect($full['json']['isActive'])->toBeFalse();
        expect($full['json']['stores'])->toBe([0]);

        expect(apiDelete("/api/rest/v2/cms-blocks/{$blockId}", $token)['status'])->toBeIn([200, 204]);
    });

    it('keeps the omitted fields of a blog category', function (): void {
        $token = serviceToken(['blog-categories/write', 'blog-categories/delete']);
        $suffix = uniqid();
        $storeId = partialUpdateStoreId();

        $create = apiPost('/api/rest/v2/blog-categories', [
            'name' => 'Partial Category',
            'urlKey' => "test-pest-partial-category-{$suffix}",
            'isActive' => true,
            'stores' => [$storeId],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $categoryId = (int) $create['json']['id'];
        $GLOBALS['_test_partial_update_blog_categories'][] = $categoryId;

        $update = apiPut("/api/rest/v2/blog-categories/{$categoryId}", ['position' => 7], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['position'])->toBe(7);
        expect($update['json']['name'])->toBe('Partial Category');
        expect($update['json']['urlKey'])->toBe("test-pest-partial-category-{$suffix}");
        expect($update['json']['isActive'])->toBeTrue();
        expect($update['json']['stores'])->toBe([$storeId]);

        $full = apiPut("/api/rest/v2/blog-categories/{$categoryId}", [
            'name' => 'Partial Category Changed',
            'urlKey' => "test-pest-partial-category-changed-{$suffix}",
            'isActive' => false,
            'stores' => ['all'],
        ], $token);
        expect($full['status'])->toBe(200);
        expect($full['json']['name'])->toBe('Partial Category Changed');
        expect($full['json']['urlKey'])->toBe("test-pest-partial-category-changed-{$suffix}");
        expect($full['json']['isActive'])->toBeFalse();
        expect($full['json']['stores'])->toBe([0]);

        expect(apiDelete("/api/rest/v2/blog-categories/{$categoryId}", $token)['status'])->toBeIn([200, 204]);
    });

});
