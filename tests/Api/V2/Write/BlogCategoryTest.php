<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Blog Category CRUD Tests
 *
 * End-to-end tests for blog category create, read, update, delete via REST,
 * including permission enforcement, tree validation and subtree delete.
 *
 * @group write
 */

afterAll(function (): void {
    foreach ($GLOBALS['_test_blog_categories'] ?? [] as $id) {
        try {
            $write = Mage::getSingleton('core/resource')->getConnection('core_write');
            $write->delete('blog_category_entity', ['entity_id = ?' => $id]);
        } catch (\Throwable $e) {
            // Ignore cleanup errors
        }
    }
});

function trackBlogCategory(int $id): void
{
    $GLOBALS['_test_blog_categories'][] = $id;
}

describe('Blog Category Permission Enforcement (REST)', function (): void {

    it('denies create without authentication', function (): void {
        $response = apiPost('/api/rest/v2/blog-categories', [
            'name' => 'No Auth Category',
        ]);

        expect($response['status'])->toBe(401);
    });

    it('denies create with customer token (wrong role)', function (): void {
        $response = apiPost('/api/rest/v2/blog-categories', [
            'name' => 'Customer Category',
        ], customerToken());

        expect($response['status'])->toBeForbidden();
    });

    it('denies create without correct permission', function (): void {
        $response = apiPost('/api/rest/v2/blog-categories', [
            'name' => 'No Permission Category',
        ], serviceToken(['blog-posts/write']));

        expect($response['status'])->toBeForbidden();
    });

});

describe('Blog Category CRUD Lifecycle (REST)', function (): void {

    it('creates → reads → updates → verifies → delete-denied → deletes → confirms gone', function (): void {
        $writeToken = serviceToken(['blog-categories/write']);
        $deleteToken = serviceToken(['blog-categories/delete']);

        $create = apiPost('/api/rest/v2/blog-categories', [
            'name' => 'Test CRUD Category',
            'urlKey' => 'test-pest-crud-category',
            'metaRobots' => 'noindex,nofollow',
            'stores' => ['all'],
        ], $writeToken);

        expect($create['status'])->toBeIn([200, 201]);
        expect($create['json'])->toHaveKey('id');
        $categoryId = (int) $create['json']['id'];
        trackBlogCategory($categoryId);

        expect($create['json']['name'])->toBe('Test CRUD Category');
        expect($create['json']['urlKey'])->toBe('test-pest-crud-category');
        expect($create['json']['metaRobots'])->toBe('noindex,nofollow');
        expect($create['json']['isActive'])->toBeTrue();
        expect($create['json']['stores'])->toBe([0]);
        expect($create['json']['createdAt'])->not->toBeEmpty();
        expect($create['json']['updatedAt'])->not->toBeEmpty();

        // Read (public, no auth)
        $read = apiGet("/api/rest/v2/blog-categories/{$categoryId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['name'])->toBe('Test CRUD Category');
        expect($read['json']['metaRobots'])->toBe('noindex,nofollow');

        // Partial update
        $update = apiPut("/api/rest/v2/blog-categories/{$categoryId}", [
            'name' => 'Test CRUD Category Updated',
            'metaRobots' => 'index,follow',
            'position' => 7,
        ], $writeToken);
        expect($update['status'])->toBe(200);
        expect($update['json']['name'])->toBe('Test CRUD Category Updated');
        expect($update['json']['metaRobots'])->toBe('index,follow');
        expect($update['json']['position'])->toBe(7);

        $verify = apiGet("/api/rest/v2/blog-categories/{$categoryId}");
        expect($verify['status'])->toBe(200);
        expect($verify['json']['name'])->toBe('Test CRUD Category Updated');
        // Untouched fields survive the partial update
        expect($verify['json']['urlKey'])->toBe('test-pest-crud-category');

        // Deny delete with only write permission
        $denyDelete = apiDelete("/api/rest/v2/blog-categories/{$categoryId}", $writeToken);
        expect($denyDelete['status'])->toBeForbidden();

        // Delete with correct permission
        $delete = apiDelete("/api/rest/v2/blog-categories/{$categoryId}", $deleteToken);
        expect($delete['status'])->toBeIn([200, 204]);

        $gone = apiGet("/api/rest/v2/blog-categories/{$categoryId}");
        expect($gone['status'])->toBeNotFound();
    });

    it('builds the tree from parentId and deletes descendants with the parent', function (): void {
        $token = serviceToken(['blog-categories/write', 'blog-categories/delete']);

        $parent = apiPost('/api/rest/v2/blog-categories', [
            'name' => 'Tree Parent',
            'urlKey' => 'test-pest-tree-parent',
        ], $token);
        expect($parent['status'])->toBeIn([200, 201]);
        $parentId = (int) $parent['json']['id'];
        trackBlogCategory($parentId);
        expect($parent['json']['level'])->toBe(1);

        $child = apiPost('/api/rest/v2/blog-categories', [
            'name' => 'Tree Child',
            'urlKey' => 'test-pest-tree-child',
            'parentId' => $parentId,
        ], $token);
        expect($child['status'])->toBeIn([200, 201]);
        $childId = (int) $child['json']['id'];
        trackBlogCategory($childId);
        expect($child['json']['level'])->toBe(2);
        expect($child['json']['path'])->toBe("{$parentId}/{$childId}");

        // Deleting the parent removes the child too
        $delete = apiDelete("/api/rest/v2/blog-categories/{$parentId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);

        expect(apiGet("/api/rest/v2/blog-categories/{$childId}")['status'])->toBeNotFound();
    });

    it('repaths the whole subtree when a category moves', function (): void {
        $token = serviceToken(['blog-categories/write', 'blog-categories/delete']);
        $suffix = substr(uniqid(), -8);

        $a = apiPost('/api/rest/v2/blog-categories', [
            'name' => 'Move Grandparent',
            'urlKey' => "test-pest-move-a-{$suffix}",
        ], $token);
        expect($a['status'])->toBeIn([200, 201]);
        $aId = (int) $a['json']['id'];
        trackBlogCategory($aId);

        $b = apiPost('/api/rest/v2/blog-categories', [
            'name' => 'Move Parent',
            'urlKey' => "test-pest-move-b-{$suffix}",
            'parentId' => $aId,
        ], $token);
        expect($b['status'])->toBeIn([200, 201]);
        $bId = (int) $b['json']['id'];
        trackBlogCategory($bId);

        $c = apiPost('/api/rest/v2/blog-categories', [
            'name' => 'Move Child',
            'urlKey' => "test-pest-move-c-{$suffix}",
            'parentId' => $bId,
        ], $token);
        expect($c['status'])->toBeIn([200, 201]);
        $cId = (int) $c['json']['id'];
        trackBlogCategory($cId);
        expect($c['json']['path'])->toBe("{$aId}/{$bId}/{$cId}");
        expect($c['json']['level'])->toBe(3);

        // Move the middle category to the root: the child must follow it.
        $move = apiPut("/api/rest/v2/blog-categories/{$bId}", [
            'parentId' => 0,
        ], $token);
        expect($move['status'])->toBe(200);
        expect($move['json']['path'])->toBe((string) $bId);
        expect($move['json']['level'])->toBe(1);

        $child = apiGet("/api/rest/v2/blog-categories/{$cId}");
        expect($child['status'])->toBe(200);
        expect($child['json']['path'])->toBe("{$bId}/{$cId}");
        expect($child['json']['level'])->toBe(2);

        // The old branch no longer owns the child: deleting it leaves the child alone.
        expect(apiDelete("/api/rest/v2/blog-categories/{$aId}", $token)['status'])->toBeIn([200, 204]);
        expect(apiGet("/api/rest/v2/blog-categories/{$cId}")['status'])->toBe(200);

        // The new branch does: deleting the moved category takes the child with it.
        expect(apiDelete("/api/rest/v2/blog-categories/{$bId}", $token)['status'])->toBeIn([200, 204]);
        expect(apiGet("/api/rest/v2/blog-categories/{$cId}")['status'])->toBeNotFound();
    });

    it('rejects an unknown parentId and a create without name', function (): void {
        $token = serviceToken(['blog-categories/write']);

        $badParent = apiPost('/api/rest/v2/blog-categories', [
            'name' => 'Orphan Category',
            'parentId' => 99999999,
        ], $token);
        expect($badParent['status'])->toBe(400);

        $noName = apiPost('/api/rest/v2/blog-categories', [
            'urlKey' => 'test-pest-no-name',
        ], $token);
        expect($noName['status'])->toBe(400);
    });

});
