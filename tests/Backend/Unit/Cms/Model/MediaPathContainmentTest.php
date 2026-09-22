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

describe('admin directive preview filter', function () {
    beforeEach(function () {
        $this->filter = Mage::getModel('cms/adminhtml_template_filter');
    });

    it('resolves a single media directive', function () {
        expect($this->filter->filter("\n {{media url=\"wysiwyg/logo.png\"}} "))
            ->toBe(Mage::getBaseDir('media') . '/wysiwyg/logo.png');
    });

    it('refuses a directive the preview does not serve', function () {
        expect(fn() => $this->filter->filter('{{config path="web/unsecure/base_url"}}'))
            ->toThrow(Mage_Core_Exception::class, 'Invalid directive.');
        expect(fn() => $this->filter->filter('{{block type="core/template" template="x.phtml"}}'))
            ->toThrow(Mage_Core_Exception::class, 'Invalid directive.');
    });

    it('refuses more than one directive or text around it', function () {
        expect(fn() => $this->filter->filter('{{media url="a.png"}}{{media url="b.png"}}'))
            ->toThrow(Mage_Core_Exception::class, 'Invalid directive.');
        expect(fn() => $this->filter->filter('x{{media url="a.png"}}'))
            ->toThrow(Mage_Core_Exception::class, 'Invalid directive.');
    });

    it('refuses plain text and an empty string', function () {
        expect(fn() => $this->filter->filter(''))
            ->toThrow(Mage_Core_Exception::class, 'Invalid directive.');
        expect(fn() => $this->filter->filter('/etc/passwd'))
            ->toThrow(Mage_Core_Exception::class, 'Invalid directive.');
    });

    it('lets a traversal inside a media directive reach the caller', function () {
        expect(fn() => $this->filter->filter('{{media url="../../app/etc/local.xml"}}'))
            ->toThrow(Mage_Core_Exception::class);
    });
});

describe('wysiwyg thumbnail resolver', function () {
    beforeEach(function () {
        $this->storage = Mage::getModel('cms/wysiwyg_images_storage');
        $this->mount = Mage::getStorage('media');
        $this->outside = 'thumb_escape_' . uniqid() . '.png';
        $this->mount->write($this->outside, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
        ));
    });

    afterEach(function () {
        $this->mount->delete($this->outside);
    });

    it('refuses to resize a file outside the current directory', function () {
        expect($this->storage->resizeOnTheFly('../' . $this->outside))->toBeFalse();
        expect($this->mount->fileExists($this->storage->getThumbsPath('wysiwyg/' . $this->outside) . '/' . $this->outside))->toBeFalse();
    });

    it('refuses a path with a directory part', function () {
        expect($this->storage->resizeOnTheFly('/' . $this->outside))->toBeFalse();
        expect($this->storage->resizeFile($this->outside))->toBeFalse();
    });

    it('keeps the thumbnail directory below the storage root', function () {
        expect($this->storage->getThumbsPath('wysiwyg/a/b/c.png'))->toBe('wysiwyg/.thumbs/a/b');
        expect($this->storage->getThumbsPath('wysiwyg/c.png'))->toBe('wysiwyg/.thumbs');
        expect($this->storage->getThumbnailPath('wysiwyg/a/c.png'))->toBe('wysiwyg/.thumbs/a/c.png');
        expect($this->storage->getThumbnailPath('other/c.png'))->toBeFalse();
    });
});

describe('wysiwyg helper ids', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('cms/wysiwyg_images');
    });

    it('round-trips a directory below the root and refuses one outside it', function () {
        $id = $this->helper->convertPathToId('wysiwyg/banners/2026');
        expect($this->helper->idDecode($id))->toBe('/banners/2026');
        expect($this->helper->convertIdToPath($id))->toBe('wysiwyg/banners/2026');
        expect($this->helper->convertIdToPath($this->helper->idEncode('')))->toBe('wysiwyg');
        expect($this->helper->convertIdToPath($this->helper->idEncode('/../catalog')))->toBeNull();
    });
});
