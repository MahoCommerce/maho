<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('admin media directive path', function () {
    beforeEach(function () {
        $this->filter = Mage::getModel('cms/adminhtml_template_filter');
    });

    it('resolves a media url to a path on the media mount', function () {
        $path = $this->filter->mediaDirective(['', 'media', ' url="wysiwyg/logo.png"']);
        expect($path)->toBe(Mage_Cms_Model_Adminhtml_Template_Filter::MEDIA_PREFIX . 'wysiwyg/logo.png');
    });

    it('rejects a media url that traverses out of the media directory', function () {
        expect(fn() => $this->filter->mediaDirective(['', 'media', ' url="../../app/etc/local.xml"']))
            ->toThrow(Mage_Core_Exception::class);
    });

    it('keeps an absolute media url on the media mount', function () {
        expect($this->filter->mediaDirective(['', 'media', ' url="/etc/passwd"']))
            ->toBe(Mage_Cms_Model_Adminhtml_Template_Filter::MEDIA_PREFIX . 'etc/passwd');
    });
});

describe('admin directive preview filter', function () {
    beforeEach(function () {
        $this->filter = Mage::getModel('cms/adminhtml_template_filter');
    });

    it('resolves a single media directive', function () {
        expect($this->filter->filter("\n {{media url=\"wysiwyg/logo.png\"}} "))
            ->toBe(Mage_Cms_Model_Adminhtml_Template_Filter::MEDIA_PREFIX . 'wysiwyg/logo.png');
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

    it('refuses an id that is not a path, such as the id of the tree root', function (string $id) {
        expect($this->helper->convertIdToPath($id))->toBeNull();
    })->with(['tree root' => ['root'], 'control character, "/a\x01b"' => ['L2EBYg--']]);

    it('shows the storage root for the tree root node', function () {
        Mage::app()->getRequest()->setParam('node', 'root');
        $helper = new Mage_Cms_Helper_Wysiwyg_Images();

        try {
            expect($helper->getCurrentPath())->toBe($helper->getStorageRootPath());
        } finally {
            Mage::app()->getRequest()->setParam('node', null);
        }
    });

    it('removes the root name from a folder only as a whole segment', function () {
        expect($this->helper->resolveFolder('wysiwyg'))->toBe('wysiwyg')
            ->and($this->helper->resolveFolder('wysiwyg/banners'))->toBe('wysiwyg/banners')
            ->and($this->helper->resolveFolder('wysiwyg-2026'))->toBe('wysiwyg/wysiwyg-2026');
    });
});

describe('admin skin directive path', function () {
    beforeEach(function () {
        $this->filter = Mage::getModel('cms/adminhtml_template_filter');
        $this->outsideDir = Mage::getBaseDir('var') . '/skin_escape_' . uniqid();
        mkdir($this->outsideDir, 0o755, true);
        file_put_contents($this->outsideDir . '/secret.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $this->skinPackage = 'skintest_' . uniqid();
        $this->skinPackageDir = Mage::getBaseDir('skin') . '/frontend/' . $this->skinPackage;
    });

    afterEach(function () {
        @unlink($this->skinPackageDir . '/default/link.svg');
        @rmdir($this->skinPackageDir . '/default');
        @rmdir($this->skinPackageDir);
        @unlink($this->outsideDir . '/secret.svg');
        @rmdir($this->outsideDir);
    });

    it('resolves a skin file of the admin theme', function () {
        $path = $this->filter->filter('{{skin url="images/logo.svg" _area="adminhtml" _package="default" _theme="default"}}');
        expect($path)->toEndWith('/skin/adminhtml/default/default/images/logo.svg')
            ->and(is_file($path))->toBeTrue();
    });

    it('refuses an area, a package or a theme that is not one directory name', function (string $directive) {
        expect(fn() => $this->filter->filter($directive))->toThrow(Mage_Core_Exception::class);
    })->with([
        'area traversal' => ['{{skin url="secret.svg" _area="../../var" _package="." _theme="."}}'],
        'package traversal' => ['{{skin url="secret.svg" _area="frontend" _package="../../../var" _theme="."}}'],
        'theme traversal' => ['{{skin url="secret.svg" _area="frontend" _package="base" _theme="../../../../var"}}'],
        'dot package' => ['{{skin url="images/logo.svg" _area="adminhtml" _package="." _theme="default"}}'],
        'slash in theme' => ['{{skin url="images/logo.svg" _area="adminhtml" _package="default" _theme="default/images"}}'],
    ]);

    it('refuses a file outside the skin directory that an area traversal names', function () {
        $directive = '{{skin url="secret.svg" _area="../../var" _package="' . basename($this->outsideDir) . '" _theme="."}}';
        expect(fn() => $this->filter->filter($directive))->toThrow(Mage_Core_Exception::class);
        expect(fn() => $this->filter->readDirectiveSvg($directive))->toThrow(Mage_Core_Exception::class);
    });

    it('refuses a url with a parent directory and a missing file', function (string $url) {
        expect(fn() => $this->filter->filter('{{skin url="' . $url . '" _area="adminhtml" _package="default" _theme="default"}}'))
            ->toThrow(Mage_Core_Exception::class);
    })->with(['../../../../app/etc/config.xml', 'images/missing.svg']);

    it('refuses a skin symlink that leads outside the skin directory', function () {
        mkdir($this->skinPackageDir . '/default', 0o755, true);
        if (!@symlink($this->outsideDir . '/secret.svg', $this->skinPackageDir . '/default/link.svg')) {
            $this->markTestSkipped('Symlinks are not supported here.');
        }

        expect(fn() => $this->filter->filter('{{skin url="link.svg" _area="frontend" _package="' . $this->skinPackage . '" _theme="default"}}'))
            ->toThrow(Mage_Core_Exception::class);
    });
});
