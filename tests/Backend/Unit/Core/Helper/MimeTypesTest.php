<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * Mage_Core_Helper_Data::getMimeTypes() replaces a hardcoded table that Mage_Uploader_Helper_File
 * held. The table needed a patch for each new format, so it knew no heic and no woff2.
 */

it('reads every MIME type of an extension', function (string $extension, string $mimeType) {
    expect(Mage::helper('core')->getMimeTypes([$extension]))->toContain($mimeType);
})->with([
    ['jpg', 'image/jpeg'],
    ['png', 'image/png'],
    ['pdf', 'application/pdf'],
    ['heic', 'image/heic'],
    ['woff2', 'font/woff2'],
]);

it('lists the second type that a browser can send for a jpg file', function () {
    // The old table returned image/jpeg alone, so server-side validation refused a valid upload.
    expect(Mage::helper('core')->getMimeTypes(['jpg']))->toContain('image/pjpeg');
});

it('ignores the case of an extension and reads a comma separated list', function () {
    expect(Mage::helper('core')->getMimeTypes('JPG, png'))->toContain('image/jpeg', 'image/png');
});

it('names no type for an extension that it does not know', function () {
    expect(Mage::helper('core')->getMimeTypes(['thisisnotanextension']))->toBe([]);
});

it('lists the types of several extensions without a duplicate', function () {
    $mimeTypes = Mage::helper('core')->getMimeTypes(['jpg', 'jpeg', 'png']);

    expect($mimeTypes)->toBe(array_values(array_unique($mimeTypes)));
});

/*
 * global/mime/types keeps the format that Magento 1 used. An XML node name cannot start with
 * a digit, so each node name is the letter x and then the extension.
 *
 * Each test below declares an extension that no other test reads, because a node set here
 * stays in the config for the rest of the run.
 */

it('adds a declared type for an extension that the built-in map does not hold', function () {
    Mage::getConfig()->setNode('global/mime/types/xmahotestfmt', 'application/x-maho-test');

    expect(Mage::helper('core')->getMimeTypes(['mahotestfmt']))->toBe(['application/x-maho-test']);
});

it('adds a declared type to the built-in list rather than replacing it', function () {
    Mage::getConfig()->setNode('global/mime/types/xflac', 'audio/x-maho-flac');

    $mimeTypes = Mage::helper('core')->getMimeTypes(['flac']);

    // The declared type comes first, and every built-in type stays.
    expect($mimeTypes[0])->toBe('audio/x-maho-flac')
        ->and($mimeTypes)->toContain('audio/flac');
});

it('names each type once when the declared type repeats a built-in one', function () {
    // audio/ogg is already a built-in type of the opus extension.
    Mage::getConfig()->setNode('global/mime/types/xopus', 'audio/ogg');

    $mimeTypes = Mage::helper('core')->getMimeTypes(['opus']);

    expect(array_count_values($mimeTypes)['audio/ogg'])->toBe(1)
        ->and($mimeTypes)->toBe(array_values(array_unique($mimeTypes)));
});

it('keeps the built-in list of an extension that the node does not name', function () {
    expect(Mage::helper('core')->getMimeTypes(['png']))->toContain('image/png', 'image/apng');
});

it('reads the x prefix of an extension that starts with a digit', function () {
    Mage::getConfig()->setNode('global/mime/types/x123maho', 'application/x-maho-123');

    expect(Mage::helper('core')->getMimeTypes(['123maho']))->toBe(['application/x-maho-123']);
});

it('names the declared type of one extension', function () {
    Mage::getConfig()->setNode('global/mime/types/xmahodecl', 'application/x-maho-declared');

    expect(Mage::helper('core')->getConfiguredMimeType('mahodecl'))->toBe('application/x-maho-declared')
        ->and(Mage::helper('core')->getConfiguredMimeType('MAHODECL'))->toBe('application/x-maho-declared');
});

it('names no declared type for an extension that the node leaves out', function () {
    expect(Mage::helper('core')->getConfiguredMimeType('txt'))->toBeNull()
        ->and(Mage::helper('core')->getConfiguredMimeType(''))->toBeNull();
});
