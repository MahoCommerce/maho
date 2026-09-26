<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;

uses(Tests\MahoBackendTestCase::class);

describe('Dataflow Parser Path Traversal Security', function () {
    beforeEach(function () {
        $this->root = sys_get_temp_dir() . '/maho_dataflow_parser_' . uniqid();
        mkdir($this->root . '/uploads', 0777, true);
        MountRegistry::register(new Mount('imports', new LocalFilesystemAdapter($this->root), $this->root));
        $this->imports = Mage::getStorage('imports');
        $this->imports->write('products.csv', "sku\nA\n");
        $this->imports->write('uploads/valid.csv', "sku\nB\n");
        $this->batchFile = Mage::getSingleton('dataflow/batch')->getIoAdapter()->getFile(true);

        $this->parse = function (string $model, string $files): void {
            Mage::app()->getRequest()->setParam('files', $files);
            Mage::getModel($model)
                ->setVar('adapter', 'catalog/convert_adapter_product')
                ->setVar('method', 'saveRow')
                ->parse();
        };
    });

    afterEach(function () {
        Mage::app()->getRequest()->setParam('files', null);
        MountRegistry::reset();
        if (is_file($this->batchFile)) {
            unlink($this->batchFile);
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    });

    it('refuses a file that is not in the upload folder', function (string $model, string $files) {
        expect(fn() => ($this->parse)($model, $files))
            ->toThrow(Mage_Core_Exception::class, 'Invalid file path.');
    })->with([
        'csv' => 'dataflow/convert_parser_csv',
        'excel' => 'dataflow/convert_parser_xml_excel',
    ])->with([
        'a file next to the upload folder' => '../products.csv',
        'two levels up' => '../../etc/passwd',
        'a dot segment bypass' => '..././etc/passwd',
        'a double slash bypass' => '....//....//etc/passwd',
        'an encoded traversal' => '%2e%2e%2fproducts.csv',
        'a double encoded traversal' => '%252e%252e%252fproducts.csv',
        'an absolute path' => '/etc/passwd',
        'a phar stream' => 'phar://malicious.phar',
        'an http stream' => 'http://evil.com/file',
        'a missing file' => 'missing.csv',
    ]);

    it('copies a file of the upload folder into the batch file', function () {
        $helper = Mage::helper('dataflow');
        $path = $helper->getUploadPath('valid.csv');

        $helper->copyToBatchFile($helper->getUploadMount(), (string) $path, 'valid.csv');

        expect($path)->toBe('uploads/valid.csv')
            ->and(file_get_contents($this->batchFile))->toBe("sku\nB\n");
    });

    it('refuses a symlink that leaves the upload folder', function () {
        if (!@symlink('/etc', $this->root . '/uploads/link')) {
            $this->markTestSkipped('Unable to create a symlink');
        }

        expect(Mage::helper('dataflow')->getUploadPath('link/passwd'))->toBeNull();
    });
})->group('security');
