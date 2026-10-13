<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\Url\StoreBaseUrlGenerator;

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Catalog_Model_Product_Attribute_Backend_Media on the media mount', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_media_backend_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root, new StoreBaseUrlGenerator('media'));
        MountRegistry::register($this->mount);

        $this->mount->write('tmp/catalog/product/a/b/x.png', 'new');
        $this->mount->write('catalog/product/a/b/x.png', 'old');

        $this->backend = new class extends Mage_Catalog_Model_Product_Attribute_Backend_Media {
            public function moveFromTmp(string $file): string
            {
                return $this->_moveImageFromTmp($file);
            }

            public function copy(string $file): string
            {
                return $this->_copyImage($file);
            }
        };
    });

    afterEach(function (): void {
        MountRegistry::reset();

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    });

    it('moves a temporary image to a free name on the mount', function (): void {
        $moved = $this->backend->moveFromTmp('/a/b/x.png.tmp');

        expect($moved)->toBe('/a/b/x_1.png')
            ->and($this->mount->read('catalog/product/a/b/x_1.png'))->toBe('new')
            ->and($this->mount->read('catalog/product/a/b/x.png'))->toBe('old')
            ->and($this->mount->fileExists('tmp/catalog/product/a/b/x.png'))->toBeFalse();
    });

    it('copies an image to the next free name on the mount', function (): void {
        $this->backend->moveFromTmp('/a/b/x.png.tmp');

        $copied = $this->backend->copy('/a/b/x.png');

        expect($copied)->toBe('/a/b/x_2.png')
            ->and($this->mount->read('catalog/product/a/b/x_2.png'))->toBe('old');
    });

    it('refuses a temporary path that leaves the temporary directory', function (): void {
        expect(fn() => $this->backend->moveFromTmp('/../../../secret.png.tmp'))
            ->toThrow(Exception::class, 'Detected malicious path');
    });
});
