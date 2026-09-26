<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;

uses(Tests\MahoBackendTestCase::class);

describe('Dataflow product import images on a remote media mount', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_import_images_' . uniqid();
        mkdir($this->root, 0777, true);
        // No local root: the mount behaves as a remote bucket
        MountRegistry::register(new Mount('media', new LocalFilesystemAdapter($this->root)));
        Mage::getStorage('media')->write('import/sub/a.jpg', 'JPG');
        Mage::getStorage('media')->write('secret.jpg', 'SECRET');

        $this->adapter = new class extends Mage_Catalog_Model_Convert_Adapter_Product {
            public function prepare(array $files): string
            {
                return $this->prepareImportImages($files);
            }

            public function release(string $directory): void
            {
                $this->releaseImportImages($directory);
            }
        };
    });

    afterEach(function (): void {
        MountRegistry::reset();
        \Maho\Io\File::rmdirRecursive($this->root, true);
    });

    it('copies the import images with their folders and deletes the copies on release', function (): void {
        $directory = $this->adapter->prepare(['sub/a.jpg', 'missing.jpg', '../secret.jpg']);

        $copied = file_get_contents($directory . '/sub/a.jpg');
        $files = glob($directory . '/*');
        $this->adapter->release($directory);

        expect($copied)->toBe('JPG')
            ->and($files)->toBe([$directory . '/sub'])
            ->and(is_dir($directory))->toBeFalse();
    });
});
