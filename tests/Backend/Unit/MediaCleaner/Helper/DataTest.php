<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;

uses(Tests\MahoBackendTestCase::class);

describe('Maho_MediaCleaner_Helper_Data', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_mediacleaner_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root);
        MountRegistry::register($this->mount);
        $this->helper = Mage::helper('mediacleaner');
    });

    afterEach(function (): void {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
        MountRegistry::reset();
    });

    it('maps each scan type to its directory on the media mount', function (string $type, ?string $directory): void {
        expect($this->helper->getStorageDirByType($type))->toBe($directory);
    })->with([
        ['category', 'catalog/category'],
        ['product', 'catalog/product'],
        ['product_cache', 'catalog/product/cache'],
        ['wysiwyg', 'wysiwyg'],
        ['unknown', null],
    ]);

    it('lists only the files below a directory, relative to it', function (): void {
        $this->mount->write('catalog/category/b.jpg', 'x');
        $this->mount->write('catalog/category/sub/a.jpg', 'x');
        $this->mount->createDirectory('catalog/category/empty');
        $this->mount->write('catalog/other.jpg', 'x');

        expect($this->helper->listAllFiles($this->mount, 'catalog/category'))->toBe(['b.jpg', 'sub/a.jpg'])
            ->and($this->helper->listAllFiles($this->mount, 'missing'))->toBe([]);
    });

    it('lists the scanned files with one deep listing for each folder, and reports each folder', function (): void {
        foreach (['root.jpg', 'a/1.jpg', 'a/b/2.jpg', 'c/3.jpg', 'c/cache/4.jpg', 'cache/5.jpg'] as $file) {
            $this->mount->write('catalog/category/' . $file, 'x');
        }
        $calls = [];

        $files = $this->helper->listScannedFiles($this->mount, 'catalog/category', function (int $done, int $total) use (&$calls): void {
            $calls[] = "{$done}/{$total}";
        });

        expect($files)->toBe(['a/1.jpg', 'a/b/2.jpg', 'c/3.jpg', 'root.jpg'])
            ->and($calls)->toBe(['1/2', '2/2']);
    });

    it('leaves out the files below an excluded folder at any depth', function (): void {
        $files = ['a/b/c.jpg', 'cache/1/x.jpg', 'a/watermark/w.jpg', 'optimized/o.jpg', 'x/.thumbs/t.jpg', 'cache.jpg'];

        expect($this->helper->filterFiles($files, 'catalog/product', []))->toBe(['a/b/c.jpg', 'cache.jpg']);
    });

    it('leaves out the files that a blacklist pattern names, relative to the media mount', function (): void {
        $files = ['images2021/a.jpg', 'landing1/a.jpg', 'landing1/a.png', 'keep/a.jpg'];

        expect($this->helper->filterFiles($files, 'wysiwyg', ['wysiwyg/images2021', 'wysiwyg/landing*/*.jpg']))
            ->toBe(['landing1/a.png', 'keep/a.jpg']);
    });

    it('finds the cache files that no page links to, and keeps an original that is in the output format', function (): void {
        $settings = new Mage_Core_Model_Image_Settings([150, 300]);
        $hash = $settings->getHash();
        $cacheFiles = [
            "{$hash}/300/a/b/kept.jpg.webp",
            "{$hash}/300/a/b/flat.webp",
            "{$hash}/300/placeholder/default/p.png.webp",
            "{$hash}/300/a/b/flat.webp.webp",
            "{$hash}/300/a/b/gone.jpg.webp",
            "{$hash}/600/a/b/kept.jpg.webp",
            "{$hash}/300/a/b/kept.jpg.png",
            '0123456789ab/300/a/b/kept.jpg.webp',
            '1/image/265x/0123abcd/a/b/kept.jpg.webp',
        ];
        $sources = ['a/b/kept.jpg' => true, 'a/b/flat.webp' => true, 'placeholder/default/p.png' => true];

        expect($this->helper->getUnusedProductCacheFiles($cacheFiles, $sources, [$hash => $settings]))->toBe([
            "{$hash}/300/a/b/flat.webp.webp",
            "{$hash}/300/a/b/gone.jpg.webp",
            "{$hash}/600/a/b/kept.jpg.webp",
            "{$hash}/300/a/b/kept.jpg.png",
            '0123456789ab/300/a/b/kept.jpg.webp',
            '1/image/265x/0123abcd/a/b/kept.jpg.webp',
        ]);
    });

    it('finds the unused cache files with one listing of the product directory', function (): void {
        $settings = Mage::getSingleton('core/image_config')->getActiveSettings('product', (int) Mage::app()->getDefaultStoreView()->getId());
        $this->mount->write("catalog/product/a/b/kept{$settings->extension}", 'x');
        $this->mount->write("catalog/product/cache/{$settings->getHash()}/{$settings->widths[0]}/a/b/kept{$settings->extension}", 'x');
        $this->mount->write('catalog/product/cache/1/image/265x/0123abcd/a/b/kept.jpg.webp', 'x');

        $calls = [];

        expect($this->helper->findUnusedProductCacheFiles($this->mount, function (int $done, int $total) use (&$calls): void {
            $calls[] = "{$done}/{$total}";
        }))->toBe(['1/image/265x/0123abcd/a/b/kept.jpg.webp'])
            // a, cache/{hash}/{width} and cache/1/image are one listing each
            ->and($calls)->toBe(['1/3', '2/3', '3/3']);
    });

    it('finds the wysiwyg files that no content names', function (): void {
        $files = ['used.jpg', 'unused.jpg', 'swatches/red.png', 'css.png'];
        $contents = ['<img src="{{media url="wysiwyg/USED.jpg"}}">', 'body { background: url(../media/wysiwyg/css.png); }'];

        expect($this->helper->getUnusedWysiwygFiles($files, $contents, true))->toBe(['unused.jpg'])
            ->and($this->helper->getUnusedWysiwygFiles($files, $contents, false))->toBe(['unused.jpg', 'swatches/red.png']);
    });

    it('deletes the file of a scan result and counts a missing file as deleted', function (): void {
        $this->mount->write('catalog/category/a.jpg', 'x');

        expect($this->helper->deleteImageFile($this->mount, 'category', 'a.jpg'))->toBeTrue()
            ->and($this->mount->fileExists('catalog/category/a.jpg'))->toBeFalse()
            ->and($this->helper->deleteImageFile($this->mount, 'category', 'a.jpg'))->toBeTrue();
    });

    it('never deletes a file outside the directory of the scan type', function (): void {
        $this->mount->write('secret.jpg', 'x');

        expect($this->helper->getImageStoragePath($this->mount, 'category', '../../secret.jpg'))->toBeNull()
            ->and($this->helper->getImageStoragePath($this->mount, 'unknown', 'secret.jpg'))->toBeNull()
            ->and($this->helper->deleteImageFile($this->mount, 'category', '../../secret.jpg'))->toBeTrue()
            ->and($this->mount->fileExists('secret.jpg'))->toBeTrue();
    });

    it('flushes a directory and keeps its siblings', function (): void {
        $this->mount->write('tmp/a.jpg', 'x');
        $this->mount->write('tmp/sub/b.jpg', 'x');
        $this->mount->write('keep/c.jpg', 'x');

        expect($this->helper->flushDirectory($this->mount, 'tmp'))->toBeTrue()
            ->and($this->mount->listContents('tmp', true)->toArray())->toBe([])
            ->and($this->mount->fileExists('keep/c.jpg'))->toBeTrue();
    });

    it('flushes the mount root and keeps the root directory', function (): void {
        $this->mount->write('a.csv', 'x');
        $this->mount->write('sub/b.csv', 'x');

        expect($this->helper->flushDirectory($this->mount, ''))->toBeTrue()
            ->and(is_dir($this->root))->toBeTrue()
            ->and($this->mount->listContents('', true)->toArray())->toBe([]);
    });
});
