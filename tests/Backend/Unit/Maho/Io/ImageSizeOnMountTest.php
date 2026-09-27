<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;

uses(Tests\MahoBackendTestCase::class);

describe('Maho\Io::getImageSizeOnMount()', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_image_size_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->path = 'i/' . uniqid() . '.png';
        $png = Maho::getImageManager()->createImage(40, 20)->fill('ff0000')
            ->encodeUsingFormat(\Intervention\Image\Format::PNG)->toString();
        new Mount('media', new LocalFilesystemAdapter($this->root), $this->root)->write($this->path, $png);
    });

    afterEach(function (): void {
        Mage::app()->cleanCache(['image_size_test']);
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    });

    it('reads the size of a file on a local mount', function (): void {
        $mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root);

        $info = \Maho\Io::getImageSizeOnMount($mount, $this->path);

        expect([$info[0], $info[1]])->toBe([40, 20]);
    });

    it('reads a file on a remote mount once and then answers from the cache', function (): void {
        $mount = new Mount('media', new LocalFilesystemAdapter($this->root));

        $first = \Maho\Io::getImageSizeOnMount($mount, $this->path, ['image_size_test']);
        $mount->delete($this->path);
        $second = \Maho\Io::getImageSizeOnMount($mount, $this->path, ['image_size_test']);

        expect([$first[0], $first[1]])->toBe([40, 20])
            ->and([$second[0], $second[1]])->toBe([40, 20]);
    });

    it('answers false for a missing file', function (): void {
        $mount = new Mount('media', new LocalFilesystemAdapter($this->root));

        expect(\Maho\Io::getImageSizeOnMount($mount, 'i/missing.png'))->toBeFalse();
    });
});
