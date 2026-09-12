<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('admin media directive path', function () {
    beforeEach(function () {
        $this->filter = Mage::getModel('cms/adminhtml_template_filter');
    });

    it('resolves a media url inside the media directory', function () {
        $path = $this->filter->mediaDirective(['', 'media', ' url="wysiwyg/logo.png"']);
        expect($path)->toBe(Mage::getBaseDir('media') . '/wysiwyg/logo.png');
    });

    it('rejects a media url that traverses out of the media directory', function () {
        expect(fn() => $this->filter->mediaDirective(['', 'media', ' url="../../app/etc/local.xml"']))
            ->toThrow(Mage_Core_Exception::class);
    });

    it('rejects an absolute media url', function () {
        expect(fn() => $this->filter->mediaDirective(['', 'media', ' url="/etc/passwd"']))
            ->toThrow(Mage_Core_Exception::class);
    });
});

describe('wysiwyg thumbnail resolver', function () {
    beforeEach(function () {
        $this->storage = Mage::getModel('cms/wysiwyg_images_storage');
        $this->root = rtrim(Mage::helper('cms/wysiwyg_images')->getStorageRoot(), '/');
        $this->outside = Mage::getBaseDir('media') . '/thumb_escape_' . uniqid() . '.png';
        file_put_contents($this->outside, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
        ));
    });

    afterEach(function () {
        unlink($this->outside);
    });

    it('refuses to resize a file outside the current directory', function () {
        $escaped = '../' . basename($this->outside);
        expect($this->storage->resizeOnTheFly($escaped))->toBeFalse();
        expect(file_exists($this->storage->getThumbsPath($this->outside) . '/' . basename($this->outside)))->toBeFalse();
    });

    it('refuses an absolute path outside the current directory', function () {
        expect($this->storage->resizeOnTheFly($this->outside))->toBeFalse();
    });
});
