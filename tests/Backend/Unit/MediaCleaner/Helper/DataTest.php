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
        expect($this->helper->getMountDirByType($type))->toBe($directory);
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

    it('leaves out the files below an excluded folder at any depth', function (): void {
        $files = ['a/b/c.jpg', 'cache/1/x.jpg', 'a/watermark/w.jpg', 'optimized/o.jpg', 'x/.thumbs/t.jpg', 'cache.jpg'];

        expect($this->helper->filterFiles($files, 'catalog/product', []))->toBe(['a/b/c.jpg', 'cache.jpg']);
    });

    it('leaves out the files that a blacklist pattern names, relative to the media mount', function (): void {
        $files = ['images2021/a.jpg', 'landing1/a.jpg', 'landing1/a.png', 'keep/a.jpg'];

        expect($this->helper->filterFiles($files, 'wysiwyg', ['wysiwyg/images2021', 'wysiwyg/landing*/*.jpg']))
            ->toBe(['landing1/a.png', 'keep/a.jpg']);
    });

    it('finds the cache files whose source image is gone', function (): void {
        $cacheFiles = [
            '1/image/265x/0123abcd/a/b/kept.jpg.webp',
            '1/image/0123abcd/a/b/kept.jpg.webp',
            '1/image/265x/0123abcd/a/b/gone.jpg.webp',
            '1/image/265x/0123abcd/a/b/kept.jpg.png',
            '1/image/265x/0123abcd/placeholder/default/p.jpg.webp',
        ];

        expect($this->helper->getUnusedProductCacheFiles($cacheFiles, ['a/b/kept.jpg' => true], '.webp'))->toBe([
            '1/image/265x/0123abcd/a/b/gone.jpg.webp',
            '1/image/265x/0123abcd/a/b/kept.jpg.png',
        ]);
    });

    it('finds the unused cache files with one listing of the product directory', function (): void {
        $this->mount->write('catalog/product/a/b/kept.jpg', 'x');
        $this->mount->write('catalog/product/cache/1/image/265x/0123abcd/a/b/kept.jpg.webp', 'x');
        $this->mount->write('catalog/product/cache/1/image/265x/0123abcd/a/b/gone.jpg.webp', 'x');

        expect($this->helper->findUnusedProductCacheFiles($this->mount, '.webp'))
            ->toBe(['1/image/265x/0123abcd/a/b/gone.jpg.webp']);
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

        expect($this->helper->getImageMountPath($this->mount, 'category', '../../secret.jpg'))->toBeNull()
            ->and($this->helper->getImageMountPath($this->mount, 'unknown', 'secret.jpg'))->toBeNull()
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
