<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function api2ImageFileName(array $data): string
{
    $class = new ReflectionClass(Mage_Catalog_Model_Api2_Product_Image_Rest_Admin_V1::class);
    $resource = $class->newInstanceWithoutConstructor();
    return $class->getMethod('_getFileName')->invoke($resource, $data);
}

describe('legacy REST product image file name', function () {
    it('keeps a plain file name and appends the extension of the mime type', function () {
        expect(api2ImageFileName(['file_name' => 'front', 'file_mime_type' => 'image/png']))->toBe('front.png');
    });

    it('defaults to image when no file name is given', function () {
        expect(api2ImageFileName(['file_mime_type' => 'image/jpeg']))->toBe('image.jpg');
    });

    it('strips directory separators and traversal from the file name', function () {
        $name = api2ImageFileName(['file_name' => '../../../public/shell', 'file_mime_type' => 'image/png']);
        expect($name)->not->toContain('/')->not->toContain('\\');
        expect(basename($name))->toBe($name);
        expect($name)->toEndWith('.png');

        $name = api2ImageFileName(['file_name' => '..\\..\\shell', 'file_mime_type' => 'image/png']);
        expect($name)->not->toContain('/')->not->toContain('\\');
    });

    it('never yields a bare dot name', function () {
        expect(api2ImageFileName(['file_name' => '..', 'file_mime_type' => 'image/png']))->toBe('image.png');
        $name = api2ImageFileName(['file_name' => '///', 'file_mime_type' => 'image/png']);
        expect(pathinfo($name, PATHINFO_FILENAME))->toMatch('/^[a-z0-9_]+$/i');
        expect($name)->toEndWith('.png');
    });
});
