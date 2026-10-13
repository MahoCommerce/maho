<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UrlGeneration\PrefixPublicUrlGenerator;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;

uses(Tests\MahoBackendTestCase::class);

const WYSIWYG_TEST_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgIB0AAAA0AAEjQ4N1AAAAAElFTkSuQmCC';

function wysiwygTestRemoveDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

describe('media browser storage on a registered media mount', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_wysiwyg_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount(
            'media',
            new LocalFilesystemAdapter($this->root),
            $this->root,
            new PrefixPublicUrlGenerator('https://cdn.test/media/'),
        );
        MountRegistry::register($this->mount);
        $this->storage = Mage::getModel('cms/wysiwyg_images_storage');
        $this->helper = Mage::helper('cms/wysiwyg_images');
    });

    afterEach(function (): void {
        wysiwygTestRemoveDir($this->root);
    });

    it('lists child directories with ids and hides the thumbnail directory', function (): void {
        $this->mount->createDirectory('wysiwyg/banners');
        $this->mount->createDirectory('wysiwyg/.thumbs');
        $this->mount->write('wysiwyg/a.png', 'x');

        $names = [];
        foreach ($this->storage->getDirsCollection('wysiwyg') as $item) {
            $names[$item->getName()] = $this->helper->convertIdToPath($item->getId());
        }

        expect($names)->toBe(['banners' => 'wysiwyg/banners']);
    });

    it('lists files with the thumbnail from one listing of the thumbnail directory', function (): void {
        $this->mount->write('wysiwyg/banners/old.png', base64_decode(WYSIWYG_TEST_PNG));
        $this->mount->write('wysiwyg/banners/notes.txt', 'text');
        $this->mount->write('wysiwyg/.thumbs/banners/old.png', base64_decode(WYSIWYG_TEST_PNG));
        $this->mount->write('wysiwyg/banners/new.png', base64_decode(WYSIWYG_TEST_PNG));
        touch($this->root . '/wysiwyg/banners/old.png', time() - 100);

        $items = [];
        foreach ($this->storage->getFilesCollection('wysiwyg/banners', 'image') as $item) {
            $items[$item->getName()] = $item;
        }

        expect(array_keys($items))->toBe(['old.png', 'new.png'])
            ->and($items['old.png']->getThumbUrl())->toStartWith('https://cdn.test/media/wysiwyg/.thumbs/banners/old.png?rand=')
            ->and($items['new.png']->getThumbUrl())->toContain('/file/' . $this->helper->idEncode('new.png') . '/node/' . $this->helper->convertPathToId('wysiwyg/banners') . '/')
            ->and($items['new.png']->getWidth())->toBe(4)
            ->and($items['old.png']->getId())->toBe($this->helper->idEncode('old.png'));
    });

    it('creates a directory below the root and refuses a name that exists', function (): void {
        $result = $this->storage->createDirectory('promo', 'wysiwyg');

        expect($result['path'])->toBe('wysiwyg/promo')
            ->and($this->helper->convertIdToPath($result['id']))->toBe('wysiwyg/promo')
            ->and($this->mount->directoryExists('wysiwyg/promo'))->toBeTrue()
            ->and(fn() => $this->storage->createDirectory('promo', 'wysiwyg'))->toThrow(Mage_Core_Exception::class);
    });

    it('deletes a directory with its thumbnails and never the root', function (): void {
        $this->mount->write('wysiwyg/promo/a.png', 'x');
        $this->mount->write('wysiwyg/.thumbs/promo/a.png', 'x');

        $this->storage->deleteDirectory('wysiwyg/promo');

        expect($this->mount->directoryExists('wysiwyg/promo'))->toBeFalse()
            ->and($this->mount->directoryExists('wysiwyg/.thumbs/promo'))->toBeFalse()
            ->and(fn() => $this->storage->deleteDirectory('wysiwyg'))->toThrow(Mage_Core_Exception::class)
            ->and(fn() => $this->storage->deleteDirectory('catalog'))->toThrow(Exception::class);
    });

    it('deletes a file with its thumbnail', function (): void {
        $this->mount->write('wysiwyg/a.png', 'x');
        $this->mount->write('wysiwyg/.thumbs/a.png', 'x');

        $this->storage->deleteFile('wysiwyg/a.png');

        expect($this->mount->fileExists('wysiwyg/a.png'))->toBeFalse()
            ->and($this->mount->fileExists('wysiwyg/.thumbs/a.png'))->toBeFalse();
    });

    it('writes a thumbnail through the mount', function (): void {
        $this->mount->write('wysiwyg/promo/pic.png', base64_decode(WYSIWYG_TEST_PNG));

        expect($this->storage->resizeFile('wysiwyg/promo/pic.png'))->toBe('wysiwyg/.thumbs/promo/pic.png')
            ->and($this->mount->fileExists('wysiwyg/.thumbs/promo/pic.png'))->toBeTrue();
    });
});
