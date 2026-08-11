<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Blog Post field coverage: shortContent round-trip, metaRobots,
 * and writable categoryIds (plain ids and {id, position} objects).
 *
 * @group write
 */

afterAll(function (): void {
    foreach ($GLOBALS['_test_blog_field_posts'] ?? [] as $id) {
        try {
            $write = Mage::getSingleton('core/resource')->getConnection('core_write');
            $write->delete('blog_post_entity', ['entity_id = ?' => $id]);
        } catch (\Throwable $e) {
            // Ignore cleanup errors
        }
    }
    foreach ($GLOBALS['_test_blog_field_categories'] ?? [] as $id) {
        try {
            $write = Mage::getSingleton('core/resource')->getConnection('core_write');
            $write->delete('blog_category_entity', ['entity_id = ?' => $id]);
        } catch (\Throwable $e) {
            // Ignore cleanup errors
        }
    }
});

function trackBlogFieldPost(int $id): void
{
    $GLOBALS['_test_blog_field_posts'][] = $id;
}

function trackBlogFieldCategory(int $id): void
{
    $GLOBALS['_test_blog_field_categories'][] = $id;
}

function createBlogFieldCategory(string $name, string $urlKey): int
{
    $response = apiPost('/api/rest/v2/blog-categories', [
        'name' => $name,
        'urlKey' => $urlKey,
        'stores' => ['all'],
    ], serviceToken(['blog-categories/write']));

    expect($response['status'])->toBeIn([200, 201]);
    $id = (int) $response['json']['id'];
    trackBlogFieldCategory($id);

    return $id;
}

describe('Blog Post shortContent and metaRobots', function (): void {

    it('round-trips shortContent and metaRobots through create, read and update', function (): void {
        $token = serviceToken(['blog-posts/write', 'blog-posts/delete']);

        $create = apiPost('/api/rest/v2/blog-posts', [
            'title' => 'Short Content Post',
            'urlKey' => 'test-pest-short-content-post',
            'content' => '<p>Full content</p>',
            'shortContent' => '<p>The short version</p>',
            'metaRobots' => 'noindex,follow',
            'isActive' => true,
            'stores' => ['all'],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $postId = (int) $create['json']['id'];
        trackBlogFieldPost($postId);

        expect($create['json']['shortContent'])->toContain('The short version');
        expect($create['json']['metaRobots'])->toBe('noindex,follow');

        // Read back: the value must come from storage, not the request echo
        $read = apiGet("/api/rest/v2/blog-posts/{$postId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['shortContent'])->toContain('The short version');
        expect($read['json']['metaRobots'])->toBe('noindex,follow');

        // Partial update of shortContent only
        $update = apiPut("/api/rest/v2/blog-posts/{$postId}", [
            'shortContent' => '<p>Updated short version</p>',
        ], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['shortContent'])->toContain('Updated short version');

        $verify = apiGet("/api/rest/v2/blog-posts/{$postId}");
        expect($verify['status'])->toBe(200);
        expect($verify['json']['shortContent'])->toContain('Updated short version');
        // Untouched fields survive the partial update
        expect($verify['json']['content'])->toContain('Full content');
        expect($verify['json']['metaRobots'])->toBe('noindex,follow');

        $delete = apiDelete("/api/rest/v2/blog-posts/{$postId}", serviceToken(['blog-posts/delete']));
        expect($delete['status'])->toBeIn([200, 204]);
    });

});

describe('Blog Post writable categoryIds', function (): void {

    it('assigns categories on create, honors positions and updates assignments', function (): void {
        $token = serviceToken(['blog-posts/write', 'blog-posts/delete']);

        $catA = createBlogFieldCategory('Post Fields Cat A', 'test-pest-post-fields-cat-a');
        $catB = createBlogFieldCategory('Post Fields Cat B', 'test-pest-post-fields-cat-b');

        $create = apiPost('/api/rest/v2/blog-posts', [
            'title' => 'Categorized Post',
            'urlKey' => 'test-pest-categorized-post',
            'content' => '<p>Categorized</p>',
            'isActive' => true,
            'stores' => ['all'],
            'categoryIds' => [$catA, ['id' => $catB, 'position' => 5]],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $postId = (int) $create['json']['id'];
        trackBlogFieldPost($postId);

        sort($create['json']['categoryIds']);
        expect($create['json']['categoryIds'])->toBe([$catA, $catB]);

        // Positions land in blog_post_category
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $rows = $read->fetchPairs($read->select()
            ->from('blog_post_category', ['category_id', 'position'])
            ->where('post_id = ?', $postId));
        expect((int) $rows[$catA])->toBe(0);
        expect((int) $rows[$catB])->toBe(5);

        // Update replaces the assignment set
        $update = apiPut("/api/rest/v2/blog-posts/{$postId}", [
            'categoryIds' => [['id' => $catB, 'position' => 2]],
        ], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['categoryIds'])->toBe([$catB]);

        $rows = $read->fetchPairs($read->select()
            ->from('blog_post_category', ['category_id', 'position'])
            ->where('post_id = ?', $postId));
        expect($rows)->toHaveCount(1);
        expect((int) $rows[$catB])->toBe(2);

        $delete = apiDelete("/api/rest/v2/blog-posts/{$postId}", serviceToken(['blog-posts/delete']));
        expect($delete['status'])->toBeIn([200, 204]);
    });

    it('rejects unknown category ids', function (): void {
        $response = apiPost('/api/rest/v2/blog-posts', [
            'title' => 'Bad Category Post',
            'urlKey' => 'test-pest-bad-category-post',
            'content' => '<p>Should fail</p>',
            'stores' => ['all'],
            'categoryIds' => [99999999],
        ], serviceToken(['blog-posts/write']));

        expect($response['status'])->toBe(400);
    });

    it('rejects malformed categoryIds entries', function (): void {
        $response = apiPost('/api/rest/v2/blog-posts', [
            'title' => 'Malformed Category Post',
            'urlKey' => 'test-pest-malformed-category-post',
            'content' => '<p>Should fail</p>',
            'stores' => ['all'],
            'categoryIds' => [['position' => 3]],
        ], serviceToken(['blog-posts/write']));

        expect($response['status'])->toBe(400);
    });

});
