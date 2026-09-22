<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * Mage_Downloadable_Helper_Download::getContentType() calls mime_content_type() first.
 * getFileType() is the fallback, so it reads the extension and never opens the file.
 */

it('reads the content type from the extension of a path', function (string $path, string $mimeType) {
    expect(Mage::helper('downloadable/file')->getFileType($path))->toBe($mimeType);
})->with([
    ['/var/download/manual.pdf', 'application/pdf'],
    ['/var/download/archive.zip', 'application/zip'],
    ['/var/download/track.mp3', 'audio/mpeg'],
]);

it('falls back to the general type for a path with no extension', function () {
    expect(Mage::helper('downloadable/file')->getFileType('/var/download/readme'))
        ->toBe('application/octet-stream');
});
