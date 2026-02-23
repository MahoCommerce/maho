<?php

declare(strict_types=1);

/**
 * API v2 Permission Denial Tests
 *
 * Cross-cutting tests that verify permission boundaries work correctly.
 * Tests that the right permissions grant access and wrong permissions deny it.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Permission Boundaries - Token Types', function (): void {

    it('allows all operations with "all" permission', function (): void {
        $token = serviceToken(['all']);

        // Create a CMS page
        $create = apiPost('/api/cms-pages', [
            'identifier' => 'test-pest-perm-all',
            'title' => 'Permission All Test',
            'content' => '<p>Test</p>',
            'isActive' => true,
            'stores' => ['all'],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $pageId = $create['json']['id'];
        trackCreated('cms_page', $pageId);

        // Update it
        $update = apiPut("/api/cms-pages/{$pageId}", ['title' => 'Updated'], $token);
        expect($update['status'])->toBe(200);

        // Delete it
        $delete = apiDelete("/api/cms-pages/{$pageId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);
    });

    it('denies with expired token', function (): void {
        $response = apiPost('/api/cms-pages', [
            'identifier' => 'test-expired',
            'title' => 'Expired Token',
            'content' => '<p>Test</p>',
        ], expiredToken());

        expect($response['status'])->toBe(401);
        expect($response['json']['message'] ?? '')->toContain('expired');
    });

    it('denies with invalid token (wrong secret)', function (): void {
        $response = apiPost('/api/cms-pages', [
            'identifier' => 'test-invalid',
            'title' => 'Invalid Token',
            'content' => '<p>Test</p>',
        ], invalidToken());

        expect($response['status'])->toBe(401);
    });

    it('allows public GET without any token', function (): void {
        $response = apiGet('/api/products');
        expect($response['status'])->toBe(200);
    });

    it('denies POST without any token', function (): void {
        $response = apiPost('/api/cms-pages', [
            'identifier' => 'test-notoken',
            'title' => 'No Token',
            'content' => '<p>Test</p>',
        ]);
        expect($response['status'])->toBe(401);
    });

});

describe('Permission Boundaries - Cross-Resource Denial', function (): void {

    it('denies CMS page write with only blog permissions', function (): void {
        $token = serviceToken(['blog-posts/write']);
        $response = apiPost('/api/cms-pages', [
            'identifier' => 'test-cross-resource',
            'title' => 'Cross Resource',
            'content' => '<p>Test</p>',
        ], $token);

        expect($response['status'])->toBeForbidden();
    });

    it('denies CMS block write with only CMS page permissions', function (): void {
        $token = serviceToken(['cms-pages/write']);
        $response = apiPost('/api/cms-blocks', [
            'identifier' => 'test-cross-block',
            'title' => 'Cross Resource Block',
            'content' => '<p>Test</p>',
        ], $token);

        expect($response['status'])->toBeForbidden();
    });

    it('denies blog post write with only CMS block permissions', function (): void {
        $token = serviceToken(['cms-blocks/write']);
        $response = apiPost('/api/blog-posts', [
            'title' => 'Cross Resource Post',
            'urlKey' => 'test-cross-post',
            'content' => '<p>Test</p>',
        ], $token);

        expect($response['status'])->toBeForbidden();
    });

    it('denies media upload with only CMS permissions', function (): void {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_') . '.png';
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tmpFile);
        imagedestroy($img);

        $token = serviceToken(['cms-pages/write', 'cms-blocks/write']);
        $response = apiPostMultipart('/api/media', ['folder' => 'test'], ['file' => $tmpFile], $token);
        unlink($tmpFile);

        expect($response['status'])->toBeForbidden();
    });

});

describe('Permission Boundaries - Operation-Level Denial', function (): void {

    it('denies delete with only write permission (CMS pages)', function (): void {
        $writeToken = serviceToken(['cms-pages/write']);
        $allToken = serviceToken(['all']);

        // Create with write permission
        $create = apiPost('/api/cms-pages', [
            'identifier' => 'test-pest-write-only-page',
            'title' => 'Write Only Page',
            'content' => '<p>Test</p>',
            'isActive' => true,
            'stores' => ['all'],
        ], $writeToken);
        expect($create['status'])->toBeIn([200, 201]);
        $pageId = $create['json']['id'];
        trackCreated('cms_page', $pageId);

        // Update succeeds with write permission
        $update = apiPut("/api/cms-pages/{$pageId}", ['title' => 'Updated'], $writeToken);
        expect($update['status'])->toBe(200);

        // Delete denied with only write permission
        $delete = apiDelete("/api/cms-pages/{$pageId}", $writeToken);
        expect($delete['status'])->toBeForbidden();

        // Clean up with all permission
        apiDelete("/api/cms-pages/{$pageId}", $allToken);
    });

    it('denies delete with only write permission (blog posts)', function (): void {
        $writeToken = serviceToken(['blog-posts/write']);
        $allToken = serviceToken(['all']);

        $create = apiPost('/api/blog-posts', [
            'title' => 'Write Only Post',
            'urlKey' => 'test-pest-write-only-post',
            'content' => '<p>Test</p>',
            'isActive' => true,
            'stores' => ['all'],
        ], $writeToken);
        expect($create['status'])->toBeIn([200, 201]);
        $postId = $create['json']['id'];
        trackCreated('blog_post', $postId);

        // Delete denied with only write permission
        $delete = apiDelete("/api/blog-posts/{$postId}", $writeToken);
        expect($delete['status'])->toBeForbidden();

        // Clean up
        apiDelete("/api/blog-posts/{$postId}", $allToken);
    });

    it('allows delete with specific delete permission', function (): void {
        $writeToken = serviceToken(['cms-pages/write']);
        $deleteToken = serviceToken(['cms-pages/delete']);

        // Create
        $create = apiPost('/api/cms-pages', [
            'identifier' => 'test-pest-delete-perm-page',
            'title' => 'Delete Permission Page',
            'content' => '<p>Test</p>',
            'isActive' => true,
            'stores' => ['all'],
        ], $writeToken);
        expect($create['status'])->toBeIn([200, 201]);
        $pageId = $create['json']['id'];
        trackCreated('cms_page', $pageId);

        // Delete with specific delete permission
        $delete = apiDelete("/api/cms-pages/{$pageId}", $deleteToken);
        expect($delete['status'])->toBeIn([200, 204]);
    });

});

describe('Permission Boundaries - Empty Permissions', function (): void {

    it('denies everything with empty permissions array', function (): void {
        $token = serviceToken([]);

        // GET public endpoint still works (no auth required)
        $publicRead = apiGet('/api/products');
        expect($publicRead['status'])->toBe(200);

        // But write to any protected endpoint is denied
        $cms = apiPost('/api/cms-pages', [
            'identifier' => 'test-empty-perm',
            'title' => 'Empty Perm',
            'content' => '<p>Test</p>',
        ], $token);
        expect($cms['status'])->toBeForbidden();

        $blog = apiPost('/api/blog-posts', [
            'title' => 'Empty Perm Post',
            'urlKey' => 'test-empty-perm-post',
            'content' => '<p>Test</p>',
        ], $token);
        expect($blog['status'])->toBeForbidden();
    });

});
