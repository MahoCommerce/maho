<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\Url\StoreBaseUrlGenerator;

uses(Tests\MahoBackendTestCase::class);

describe('Mage_ImportExport_Model_Import_Uploader move() on the media mount', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_import_uploader_' . uniqid();
        $this->tmpDir = $this->root . '/tmp';
        mkdir($this->tmpDir, 0777, true);
        // No local root: the mount behaves as a remote bucket
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root . '/media'), null, new StoreBaseUrlGenerator('media'));
        MountRegistry::register($this->mount);

        $this->png = Maho::getImageManager()->createImage(10, 10)->fill('ff0000')
            ->encodeUsingFormat(\Intervention\Image\Format::PNG)->toString();
        $this->workingFiles = fn(): array => glob(Mage_ImportExport_Model_Import::getWorkingDir() . 'import-*') ?: [];

        $this->uploader = new Mage_ImportExport_Model_Import_Uploader();
        $this->uploader->setTrustedMedia(true);
        $this->uploader->init();
        $this->uploader->setDestStoragePath('catalog/product');
    });

    afterEach(function (): void {
        MountRegistry::reset();
        \Maho\Io\File::rmdirRecursive($this->root, true);
    });

    it('stores a file from the local tmp folder once and reuses it on the second move', function (): void {
        file_put_contents($this->tmpDir . '/a.png', $this->png);
        $this->uploader->setTmpDir($this->tmpDir);

        $first = $this->uploader->move('a.png');
        $second = $this->uploader->move('a.png');

        $files = array_map(fn($item) => $item->path(), $this->mount->listFiles('catalog/product')->toArray());
        expect($first['file'])->toBe('/a/_/a.png')
            ->and($second['file'])->toBe($first['file'])
            ->and($files)->toBe(['catalog/product/a/_/a.png']);
    });

    it('reads the file below import/ on the mount and leaves no local copy behind', function (): void {
        $this->mount->write('import/a.png', $this->png);
        $before = ($this->workingFiles)();

        $result = $this->uploader->move('a.png');

        expect($result['file'])->toBe('/a/_/a.png')
            ->and($this->mount->fileExists('catalog/product/a/_/a.png'))->toBeTrue()
            ->and($this->mount->read('catalog/product/a/_/a.png'))->toBe($this->png)
            ->and(($this->workingFiles)())->toBe($before);
    });

    it('names the mount in the error when the file is missing', function (): void {
        expect(fn() => $this->uploader->move('missing.png'))
            ->toThrow(Mage_Core_Exception::class, 'import/ on the media mount');
    });
});
