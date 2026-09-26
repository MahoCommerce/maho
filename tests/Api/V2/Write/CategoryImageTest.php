<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ApiV2Helper;

/**
 * API v2 Category Image Tests
 *
 * Upload and removal of the image of a category through POST and DELETE
 * /categories/{id}/image, in the global scope and in a store view, and the
 * file name check of the writable image field.
 *
 * @group write
 */

const CATEGORY_IMAGE_STORE_CODE = 'default-fr';

afterAll(function (): void {
    cleanupTestData();
    foreach (categoryImageTestFiles() as $image) {
        $path = categoryImageMediaPath($image);
        if (is_file($path)) {
            unlink($path);
        }
    }
});

/**
 * The images that the tests upload, so afterAll() can delete the files.
 *
 * @return list<string>
 */
function categoryImageTestFiles(?string $add = null): array
{
    static $files = [];
    if ($add !== null && $add !== '') {
        $files[] = $add;
    }
    return $files;
}

function categoryImageMediaPath(string $image): string
{
    ApiV2Helper::ensureMahoBootstrapped();
    return Mage::getBaseDir('media') . '/catalog/category/' . $image;
}

/**
 * Another process (the API server) creates and deletes the files, so the stat cache of this process can be stale.
 */
function categoryImageFileExists(string $image): bool
{
    clearstatcache();
    return is_file(categoryImageMediaPath($image));
}

function categoryImagePng(int $red = 255): string
{
    $img = imagecreatetruecolor(2, 2);
    imagefill($img, 0, 0, imagecolorallocate($img, $red, 0, 0));
    ob_start();
    imagepng($img);
    return base64_encode((string) ob_get_clean());
}

/**
 * The file name from the image URL of a category response.
 */
function categoryImageName(?string $url): ?string
{
    if ($url === null || $url === '') {
        return null;
    }
    $name = basename((string) parse_url($url, PHP_URL_PATH));
    categoryImageTestFiles($name);
    return $name;
}

/**
 * The image values of a category keyed by store_id, straight from EAV.
 *
 * @return array<int, ?string>
 */
function categoryImageRowsByStore(int $categoryId): array
{
    ApiV2Helper::ensureMahoBootstrapped();
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $attributeId = (int) Mage::getSingleton('eav/config')
        ->getAttribute(Mage_Catalog_Model_Category::ENTITY, 'image')
        ->getId();

    $select = $adapter->select()
        ->from($resource->getTableName('catalog_category_entity_varchar'), ['store_id', 'value'])
        ->where('entity_id = ?', $categoryId)
        ->where('attribute_id = ?', $attributeId);

    $rows = [];
    foreach ($adapter->fetchAll($select) as $row) {
        $rows[(int) $row['store_id']] = $row['value'];
    }
    return $rows;
}

function categoryImageStoreId(): int
{
    ApiV2Helper::ensureMahoBootstrapped();
    return (int) Mage::app()->getStore(CATEGORY_IMAGE_STORE_CODE)->getId();
}

function createCategoryImageTestCategory(string $token): int
{
    $create = apiPost('/api/rest/v2/categories', [
        'name' => 'Pest Category Image ' . substr(uniqid(), -8),
        'isActive' => false,
        'includeInMenu' => false,
    ], $token);
    expect($create['status'])->toBeIn([200, 201]);
    $categoryId = (int) $create['json']['id'];
    trackCreated('category', $categoryId);

    return $categoryId;
}

function uploadCategoryImage(int $categoryId, string $base64, string $filename, ?string $token, string $query = ''): array
{
    $response = apiPost("/api/rest/v2/categories/{$categoryId}/image{$query}", [
        'base64' => $base64,
        'filename' => $filename,
    ], $token);
    categoryImageName($response['json']['image'] ?? null);

    return $response;
}

describe('Category Image Permission Enforcement', function (): void {

    it('denies upload and delete without authentication', function (): void {
        $categoryId = createCategoryImageTestCategory(serviceToken(['categories/write', 'categories/delete']));

        expect(uploadCategoryImage($categoryId, categoryImagePng(), 'guest.png', null)['status'])->toBe(401);
        expect(apiDelete("/api/rest/v2/categories/{$categoryId}/image")['status'])->toBe(401);
    });

    it('denies upload and delete with a customer token', function (): void {
        $categoryId = createCategoryImageTestCategory(serviceToken(['categories/write', 'categories/delete']));

        expect(uploadCategoryImage($categoryId, categoryImagePng(), 'customer.png', customerToken())['status'])->toBeForbidden();
        expect(apiDelete("/api/rest/v2/categories/{$categoryId}/image", customerToken())['status'])->toBeForbidden();
    });

    it('denies upload and delete without the categories/write permission', function (): void {
        $categoryId = createCategoryImageTestCategory(serviceToken(['categories/write', 'categories/delete']));
        $token = serviceToken(['products/write', 'categories/delete']);

        expect(uploadCategoryImage($categoryId, categoryImagePng(), 'denied.png', $token)['status'])->toBeForbidden();
        expect(apiDelete("/api/rest/v2/categories/{$categoryId}/image", $token)['status'])->toBeForbidden();
    });

    it('denies a global write to a store-restricted token', function (): void {
        $categoryId = createCategoryImageTestCategory(serviceToken(['categories/write', 'categories/delete']));
        $token = serviceToken(['categories/write'], [1]);

        expect(uploadCategoryImage($categoryId, categoryImagePng(), 'restricted.png', $token)['status'])->toBeForbidden();
        expect(apiDelete("/api/rest/v2/categories/{$categoryId}/image", $token)['status'])->toBeForbidden();
    });

});

describe('Category Image Upload, Replace and Delete', function (): void {

    it('uploads, replaces and deletes the global image of a category', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $categoryId = createCategoryImageTestCategory($token);
        $name = 'pest-category-image-' . substr(uniqid(), -8);

        // 1. Upload
        $upload = uploadCategoryImage($categoryId, categoryImagePng(), "{$name}.png", $token);
        expect($upload['status'])->toBe(200);
        expect($upload['json']['image'])->toEndWith("/catalog/category/{$name}.png");
        expect(categoryImageFileExists("{$name}.png"))->toBeTrue();
        expect(Maho\Io::getImageSize(categoryImageMediaPath("{$name}.png"))[2] ?? null)->toBe(IMAGETYPE_PNG);
        expect(categoryImageRowsByStore($categoryId))->toBe([0 => "{$name}.png"]);

        // The response has the shape of GET /categories/{id}
        $read = apiGet("/api/rest/v2/categories/{$categoryId}", $token);
        expect($read['status'])->toBe(200);
        expect($read['json']['image'])->toBe($upload['json']['image']);
        expect(array_keys($upload['json']))->toEqualCanonicalizing(array_keys($read['json']));

        // 2. Replace with a file of the same name: the admin naming adds a number, and the old file goes
        $replace = uploadCategoryImage($categoryId, categoryImagePng(0), "{$name}.png", $token);
        expect($replace['status'])->toBe(200);
        expect($replace['json']['image'])->toEndWith("/catalog/category/{$name}_1.png");
        expect(categoryImageFileExists("{$name}_1.png"))->toBeTrue();
        expect(categoryImageFileExists("{$name}.png"))->toBeFalse();

        // 3. Delete
        $delete = apiDelete("/api/rest/v2/categories/{$categoryId}/image", $token);
        expect($delete['status'])->toBe(204);
        expect(categoryImageFileExists("{$name}_1.png"))->toBeFalse();

        $after = apiGet("/api/rest/v2/categories/{$categoryId}", $token);
        expect($after['json']['image'] ?? null)->toBeNull();

        // A second delete finds no image and still succeeds
        expect(apiDelete("/api/rest/v2/categories/{$categoryId}/image", $token)['status'])->toBe(204);
    });

    it('sets and removes the image of one store view only', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $categoryId = createCategoryImageTestCategory($token);
        $storeId = categoryImageStoreId();
        $store = '?store=' . CATEGORY_IMAGE_STORE_CODE;
        $name = 'pest-category-image-' . substr(uniqid(), -8);

        $global = uploadCategoryImage($categoryId, categoryImagePng(), "{$name}-global.png", $token);
        expect($global['status'])->toBe(200);

        // A store view upload writes only the image of that store view
        $upload = uploadCategoryImage($categoryId, categoryImagePng(0), "{$name}-store.png", $token, $store);
        expect($upload['status'])->toBe(200);
        expect($upload['json']['image'])->toEndWith("/catalog/category/{$name}-store.png");
        expect($upload['json']['storeOverrides'])->toBe(['image']);
        expect(categoryImageRowsByStore($categoryId))->toBe([
            0 => "{$name}-global.png",
            $storeId => "{$name}-store.png",
        ]);
        // The global image still uses the old file, so the file stays
        expect(categoryImageFileExists("{$name}-global.png"))->toBeTrue();
        expect(categoryImageFileExists("{$name}-store.png"))->toBeTrue();

        $read = apiGet("/api/rest/v2/categories/{$categoryId}{$store}", $token);
        expect($read['json']['image'])->toBe($upload['json']['image']);
        expect(array_keys($upload['json']))->toEqualCanonicalizing(array_keys($read['json']));
        expect(apiGet("/api/rest/v2/categories/{$categoryId}", $token)['json']['image'])
            ->toEndWith("/catalog/category/{$name}-global.png");

        // A store view delete removes the image of that store view only
        $delete = apiDelete("/api/rest/v2/categories/{$categoryId}/image{$store}", $token);
        expect($delete['status'])->toBe(204);
        expect(categoryImageRowsByStore($categoryId))->toBe([0 => "{$name}-global.png", $storeId => null]);
        expect(categoryImageFileExists("{$name}-store.png"))->toBeFalse();
        expect(categoryImageFileExists("{$name}-global.png"))->toBeTrue();

        expect(apiGet("/api/rest/v2/categories/{$categoryId}{$store}", $token)['json']['image'] ?? null)->toBeNull();
        expect(apiGet("/api/rest/v2/categories/{$categoryId}", $token)['json']['image'])
            ->toEndWith("/catalog/category/{$name}-global.png");
    });

    it('answers 404 for an unknown category', function (): void {
        $token = serviceToken(['categories/write']);

        expect(uploadCategoryImage(999999999, categoryImagePng(), 'missing.png', $token)['status'])->toBeNotFound();
        expect(apiDelete('/api/rest/v2/categories/999999999/image', $token)['status'])->toBeNotFound();
    });

});

describe('Category Image Upload Validation', function (): void {

    it('rejects an invalid upload with 400 and keeps no file', function (array $body, string $message): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $categoryId = createCategoryImageTestCategory($token);
        ApiV2Helper::ensureMahoBootstrapped();
        $tmpFilesBefore = glob(Mage::getBaseDir('tmp') . '/api_image_*') ?: [];

        $response = apiPost("/api/rest/v2/categories/{$categoryId}/image", $body, $token);
        categoryImageName($response['json']['image'] ?? null);
        expect($response['status'])->toBe(400);
        expect($response['json']['message'] ?? '')->toContain($message);

        $read = apiGet("/api/rest/v2/categories/{$categoryId}", $token);
        expect($read['json']['image'] ?? null)->toBeNull();
        expect(categoryImageRowsByStore($categoryId))->toBe([]);
        expect(glob(Mage::getBaseDir('tmp') . '/api_image_*') ?: [])->toBe($tmpFilesBefore);
    })->with([
        'invalid base64' => [['base64' => '***not base64***', 'filename' => 'bad.png'], 'Invalid base64'],
        'not an image' => [['base64' => base64_encode('<?php echo "hello";'), 'filename' => 'text.png'], 'not a valid'],
        'disallowed extension' => [['base64' => categoryImagePng(), 'filename' => 'script.php'], 'extension must be one of'],
        'no extension' => [['base64' => categoryImagePng(), 'filename' => 'image'], 'extension must be one of'],
        'missing base64' => [['filename' => 'empty.png'], 'base64 is required'],
        'missing filename' => [['base64' => categoryImagePng()], 'filename is required'],
        'too large' => [[
            // The limit is 5 MB of decoded data, so this string is one base64 block too long
            'base64' => str_repeat('A', (int) ceil(5 * 1024 * 1024 / 3) * 4 + 4),
            'filename' => 'large.png',
        ], 'larger than 5 MB'],
    ]);

    it('saves the file under media/catalog/category when the filename has a path', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $categoryId = createCategoryImageTestCategory($token);
        $name = 'pest-category-image-' . substr(uniqid(), -8);

        $upload = uploadCategoryImage($categoryId, categoryImagePng(), "../../{$name}.png", $token);
        expect($upload['status'])->toBe(200);
        expect($upload['json']['image'])->toEndWith("/catalog/category/{$name}.png");
        expect(categoryImageFileExists("{$name}.png"))->toBeTrue();
    });

});

describe('Category Image File Name Field', function (): void {

    it('rejects an unsafe image file name on create and update', function (string $image): void {
        $token = serviceToken(['categories/write', 'categories/delete']);

        $create = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Category Image Path ' . substr(uniqid(), -8),
            'isActive' => false,
            'image' => $image,
        ], $token);
        if (isset($create['json']['id'])) {
            trackCreated('category', (int) $create['json']['id']);
        }
        expect($create['status'])->toBe(400);

        $categoryId = createCategoryImageTestCategory($token);
        $update = apiPut("/api/rest/v2/categories/{$categoryId}", ['image' => $image], $token);
        expect($update['status'])->toBe(400);
        expect(categoryImageRowsByStore($categoryId))->toBe([]);
    })->with([
        'absolute path' => ['/etc/passwd.png'],
        'absolute Windows path' => ['C:\\images\\photo.png'],
        'drive letter' => ['C:photo.png'],
        'parent segment' => ['../../photo.png'],
        'http scheme' => ['http://example.com/photo.png'],
        'stream wrapper' => ['phar:photo.png'],
        'data scheme' => ['data:photo.png'],
    ]);

    it('keeps a bare file name working and removes the image with an empty string', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $categoryId = createCategoryImageTestCategory($token);

        $update = apiPut("/api/rest/v2/categories/{$categoryId}", ['image' => 'pest-first-image.webp'], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['image'])->toEndWith('/catalog/category/pest-first-image.webp');

        $clear = apiPut("/api/rest/v2/categories/{$categoryId}", ['image' => ''], $token);
        expect($clear['status'])->toBe(200);
        expect($clear['json']['image'] ?? null)->toBeNull();
    });

});
