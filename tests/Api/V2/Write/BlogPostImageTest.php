<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ApiV2Helper;

/**
 * API v2 Blog Post Image Tests
 *
 * Upload and removal of the image of a blog post through
 * POST and DELETE /blog-posts/{id}/image, and the path check of the writable image field.
 *
 * @group write
 */

afterAll(function (): void {
    foreach (blogImageTestFiles() as $image) {
        $path = blogImageMediaPath($image);
        if (is_file($path)) {
            unlink($path);
        }
    }
    cleanupTestData();
});

/**
 * The images that the tests upload, so afterAll() can delete the files.
 *
 * @return list<string>
 */
function blogImageTestFiles(?string $add = null): array
{
    static $files = [];
    if ($add !== null && $add !== '') {
        $files[] = $add;
    }
    return $files;
}

function blogImageMediaPath(string $image): string
{
    ApiV2Helper::ensureMahoBootstrapped();
    return Mage::getBaseDir('media') . '/blog/' . $image;
}

function blogImagePng(int $red = 255): string
{
    $img = imagecreatetruecolor(2, 2);
    imagefill($img, 0, 0, imagecolorallocate($img, $red, 0, 0));
    ob_start();
    imagepng($img);
    return base64_encode((string) ob_get_clean());
}

function createBlogImageTestPost(string $token, array $extra = []): int
{
    $create = apiPost('/api/rest/v2/blog-posts', [
        'title' => 'Pest Blog Image Post',
        'urlKey' => 'pest-blog-image-' . uniqid(),
        'content' => '<p>Created by the blog post image test</p>',
        'isActive' => false,
        ...$extra,
    ], $token);
    expect($create['status'])->toBeIn([200, 201]);
    $postId = (int) $create['json']['id'];
    trackCreated('blog_post', $postId);

    return $postId;
}

function uploadBlogImage(int $postId, string $base64, string $filename, ?string $token): array
{
    $response = apiPost("/api/rest/v2/blog-posts/{$postId}/image", [
        'base64' => $base64,
        'filename' => $filename,
    ], $token);
    blogImageTestFiles($response['json']['image'] ?? null);

    return $response;
}

describe('Blog Post Image Permission Enforcement', function (): void {

    it('denies upload and delete without authentication', function (): void {
        $postId = createBlogImageTestPost(serviceToken(['blog-posts/write']));

        expect(uploadBlogImage($postId, blogImagePng(), 'guest.png', null)['status'])->toBe(401);
        expect(apiDelete("/api/rest/v2/blog-posts/{$postId}/image")['status'])->toBe(401);
    });

    it('denies upload and delete with a customer token', function (): void {
        $postId = createBlogImageTestPost(serviceToken(['blog-posts/write']));

        expect(uploadBlogImage($postId, blogImagePng(), 'customer.png', customerToken())['status'])->toBeForbidden();
        expect(apiDelete("/api/rest/v2/blog-posts/{$postId}/image", customerToken())['status'])->toBeForbidden();
    });

    it('denies upload and delete without the blog-posts/write permission', function (): void {
        $postId = createBlogImageTestPost(serviceToken(['blog-posts/write']));
        $token = serviceToken(['cms-pages/write', 'blog-posts/delete']);

        expect(uploadBlogImage($postId, blogImagePng(), 'denied.png', $token)['status'])->toBeForbidden();
        expect(apiDelete("/api/rest/v2/blog-posts/{$postId}/image", $token)['status'])->toBeForbidden();
    });

    it('denies a store-restricted token on a post of all stores', function (): void {
        $postId = createBlogImageTestPost(serviceToken(['blog-posts/write']), ['stores' => ['all']]);
        $token = serviceToken(['blog-posts/write'], [1]);

        expect(uploadBlogImage($postId, blogImagePng(), 'restricted.png', $token)['status'])->toBeForbidden();
        expect(apiDelete("/api/rest/v2/blog-posts/{$postId}/image", $token)['status'])->toBeForbidden();
    });

});

describe('Blog Post Image Upload, Replace and Delete', function (): void {

    it('uploads, replaces and deletes the image of a post', function (): void {
        $token = serviceToken(['blog-posts/write']);
        $postId = createBlogImageTestPost($token);
        $name = 'pest-blog-image-' . substr(uniqid(), -8);

        // 1. Upload
        $upload = uploadBlogImage($postId, blogImagePng(), "{$name}.png", $token);
        expect($upload['status'])->toBe(200);
        $first = $upload['json']['image'];
        expect($first)->toBe("{$name}.png");
        expect($upload['json']['imageUrl'])->toEndWith("/blog/{$first}");
        expect(is_file(blogImageMediaPath($first)))->toBeTrue();
        expect(Maho\Io::getImageSize(blogImageMediaPath($first))[2] ?? null)->toBe(IMAGETYPE_PNG);

        // The response has the shape of GET /blog-posts/{id}
        $read = apiGet("/api/rest/v2/blog-posts/{$postId}", $token);
        expect($read['status'])->toBe(200);
        expect($read['json']['image'])->toBe($first);
        expect(array_keys($upload['json']))->toEqualCanonicalizing(array_keys($read['json']));

        // 2. Replace with a file of the same name: the admin naming adds a number, and the old file goes
        $replace = uploadBlogImage($postId, blogImagePng(0), "{$name}.png", $token);
        expect($replace['status'])->toBe(200);
        $second = $replace['json']['image'];
        expect($second)->toBe("{$name}_1.png");
        expect(is_file(blogImageMediaPath($second)))->toBeTrue();
        expect(is_file(blogImageMediaPath($first)))->toBeFalse();

        // 3. Delete
        $delete = apiDelete("/api/rest/v2/blog-posts/{$postId}/image", $token);
        expect($delete['status'])->toBe(204);
        expect(is_file(blogImageMediaPath($second)))->toBeFalse();

        $after = apiGet("/api/rest/v2/blog-posts/{$postId}", $token);
        expect($after['json']['image'] ?? null)->toBeEmpty();
        expect($after['json']['imageUrl'] ?? null)->toBeNull();

        // A second delete finds no image and still succeeds
        expect(apiDelete("/api/rest/v2/blog-posts/{$postId}/image", $token)['status'])->toBe(204);
    });

    it('answers 404 for an unknown post', function (): void {
        $token = serviceToken(['blog-posts/write']);

        expect(uploadBlogImage(999999999, blogImagePng(), 'missing.png', $token)['status'])->toBeNotFound();
        expect(apiDelete('/api/rest/v2/blog-posts/999999999/image', $token)['status'])->toBeNotFound();
    });

});

describe('Blog Post Image Upload Validation', function (): void {

    it('rejects an invalid upload with 400 and keeps no file', function (array $body, string $message): void {
        $token = serviceToken(['blog-posts/write']);
        $postId = createBlogImageTestPost($token);
        ApiV2Helper::ensureMahoBootstrapped();
        $tmpFilesBefore = glob(Mage::getBaseDir('tmp') . '/blog_image_*') ?: [];

        $response = apiPost("/api/rest/v2/blog-posts/{$postId}/image", $body, $token);
        blogImageTestFiles($response['json']['image'] ?? null);
        expect($response['status'])->toBe(400);
        expect($response['json']['message'] ?? '')->toContain($message);

        $read = apiGet("/api/rest/v2/blog-posts/{$postId}", $token);
        expect($read['json']['image'] ?? null)->toBeEmpty();
        expect(glob(Mage::getBaseDir('tmp') . '/blog_image_*') ?: [])->toBe($tmpFilesBefore);
    })->with([
        'invalid base64' => [['base64' => '***not base64***', 'filename' => 'bad.png'], 'Invalid base64'],
        'not an image' => [['base64' => base64_encode('<?php echo "hello";'), 'filename' => 'text.png'], 'not a valid'],
        'disallowed extension' => [['base64' => blogImagePng(), 'filename' => 'script.php'], 'extension must be one of'],
        'no extension' => [['base64' => blogImagePng(), 'filename' => 'image'], 'extension must be one of'],
        'missing base64' => [['filename' => 'empty.png'], 'base64 is required'],
        'missing filename' => [['base64' => blogImagePng()], 'filename is required'],
        'too large' => [[
            // The limit is 5 MB of decoded data, so this string is one base64 block too long
            'base64' => str_repeat('A', (int) ceil(5 * 1024 * 1024 / 3) * 4 + 4),
            'filename' => 'large.png',
        ], 'larger than 5 MB'],
    ]);

    it('saves the file under media/blog when the filename has a path', function (): void {
        $token = serviceToken(['blog-posts/write']);
        $postId = createBlogImageTestPost($token);
        $name = 'pest-blog-image-' . substr(uniqid(), -8);

        $upload = uploadBlogImage($postId, blogImagePng(), "../../{$name}.png", $token);
        expect($upload['status'])->toBe(200);
        expect($upload['json']['image'])->toBe("{$name}.png");
        expect(is_file(blogImageMediaPath("{$name}.png")))->toBeTrue();
    });

});

describe('Blog Post Image Path Field', function (): void {

    it('rejects an unsafe image path on create and update', function (string $path): void {
        $token = serviceToken(['blog-posts/write']);

        $create = apiPost('/api/rest/v2/blog-posts', [
            'title' => 'Pest Blog Image Path',
            'urlKey' => 'pest-blog-image-path-' . uniqid(),
            'content' => '<p>Should fail</p>',
            'image' => $path,
        ], $token);
        if (isset($create['json']['id'])) {
            trackCreated('blog_post', (int) $create['json']['id']);
        }
        expect($create['status'])->toBe(400);

        $postId = createBlogImageTestPost($token);
        $update = apiPut("/api/rest/v2/blog-posts/{$postId}", ['image' => $path], $token);
        expect($update['status'])->toBe(400);
    })->with([
        'absolute path' => ['/etc/passwd'],
        'absolute Windows path' => ['C:\\images\\photo.png'],
        'backslash root' => ['\\images\\photo.png'],
        'parent segment' => ['../../app/etc/local.xml'],
        'inner parent segment' => ['beauty/../../photo.png'],
        'backslash parent segment' => ['beauty\\..\\..\\photo.png'],
        'http scheme' => ['http://example.com/photo.png'],
        'stream wrapper' => ['phar://photo.png'],
    ]);

    it('keeps a relative image path working and removes the image with an empty string', function (): void {
        $token = serviceToken(['blog-posts/write']);
        $postId = createBlogImageTestPost($token, ['image' => 'pest/first-image.webp']);

        $read = apiGet("/api/rest/v2/blog-posts/{$postId}", $token);
        expect($read['json']['image'])->toBe('pest/first-image.webp');
        expect($read['json']['imageUrl'])->toEndWith('/blog/pest/first-image.webp');

        $update = apiPut("/api/rest/v2/blog-posts/{$postId}", ['image' => 'pest/second..image.webp'], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['image'])->toBe('pest/second..image.webp');

        $clear = apiPut("/api/rest/v2/blog-posts/{$postId}", ['image' => ''], $token);
        expect($clear['status'])->toBe(200);
        expect($clear['json']['image'] ?? null)->toBeEmpty();
        expect($clear['json']['imageUrl'] ?? null)->toBeNull();
    });

});
