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

describe('Dataflow files on the exports and imports mounts', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_dataflow_' . uniqid();
        foreach (['exports', 'imports'] as $name) {
            mkdir($this->root . '/' . $name, 0777, true);
            MountRegistry::register(new Mount($name, new LocalFilesystemAdapter($this->root . '/' . $name), $this->root . '/' . $name));
        }
        $this->exports = Mage::getStorage('exports');
        $this->imports = Mage::getStorage('imports');
        $this->helper = Mage::helper('dataflow');
        $this->batchFile = Mage::getSingleton('dataflow/batch')->getIoAdapter()->getFile(true);
        $this->adapter = function (string $path, string $filename): Mage_Dataflow_Model_Convert_Adapter_Io {
            $adapter = Mage::getModel('dataflow/convert_adapter_io');
            $adapter->setVar('type', 'file')->setVar('path', $path)->setVar('filename', $filename);
            return $adapter;
        };
    });

    afterEach(function (): void {
        MountRegistry::reset();
        if (is_file($this->batchFile)) {
            unlink($this->batchFile);
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    });

    it('maps a profile path to a mount and a folder', function (string $path, ?string $mount, ?string $folder): void {
        $location = $this->helper->getStorageLocation($path);

        expect($location === null ? null : $location[0]->name())->toBe($mount)
            ->and($location[1] ?? null)->toBe($folder);
    })->with([
        'the export folder' => ['var/export', 'exports', ''],
        'a folder below it' => ['var/export/daily', 'exports', 'daily'],
        'the import folder' => ['var/import/', 'imports', ''],
        'another var folder' => ['var/log', null, null],
        'a dot segment' => ['var/export/../../app/etc', null, null],
    ]);

    it('saves an export from the batch file to the exports mount', function (): void {
        file_put_contents($this->batchFile, "sku,name\nA,Alpha\n");

        ($this->adapter)('var/export/daily', 'products.csv')->save();

        expect($this->exports->read('daily/products.csv'))->toBe("sku,name\nA,Alpha\n");
    });

    it('loads an import from the imports mount into the batch file', function (): void {
        $this->imports->write('products.csv', "sku\nB\n");

        ($this->adapter)('var/import', 'products.csv')->load();

        expect(file_get_contents($this->batchFile))->toBe("sku\nB\n");
    });

    it('refuses a profile file outside the export and import folders', function (): void {
        expect(fn() => ($this->adapter)('var/export', '../../app/etc/local.xml')->load())
            ->toThrow(Mage_Core_Exception::class);
    });

    it('stores a profile upload on the imports mount and lists it by extension', function (): void {
        $local = (string) tempnam(sys_get_temp_dir(), 'maho_dataflow_upload_');
        file_put_contents($local, "sku\n");

        $this->helper->storeUpload($local, 'import-1_products.csv');
        $this->imports->write('uploads/notes.xml', '<x/>');

        expect(is_file($local))->toBeFalse()
            ->and($this->imports->read('uploads/import-1_products.csv'))->toBe("sku\n")
            ->and($this->helper->getUploadedFiles('csv'))->toBe(['import-1_products.csv'])
            ->and($this->helper->getUploadPath('../../secret.csv'))->toBeNull();
    });
});
