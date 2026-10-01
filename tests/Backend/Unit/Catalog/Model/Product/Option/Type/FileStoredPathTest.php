<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Catalog_Model_Product_Option_Type_File::resolveStoredPath()', function () {
    beforeEach(function () {
        $this->model = Mage::getModel('catalog/product_option_type_file');
        $this->quoteRel = $this->model->getQuoteTargetDir(true);
        $this->orderRel = $this->model->getOrderTargetDir(true);
    });

    it('resolves a quote path inside the quote directory', function () {
        $value = ['quote_path' => $this->quoteRel . '/a/b/abc123.txt'];
        expect($this->model->resolveStoredPath($value, 'quote_path'))
            ->toBe($this->model->getQuoteTargetDir() . '/a/b/abc123.txt');
    });

    it('resolves an order path inside the order directory', function () {
        $value = ['order_path' => $this->orderRel . '/a/b/abc123.txt'];
        expect($this->model->resolveStoredPath($value, 'order_path'))
            ->toBe($this->model->getOrderTargetDir() . '/a/b/abc123.txt');
    });

    it('rejects a quote path that traverses out of the quote directory', function () {
        $value = ['quote_path' => $this->quoteRel . '/../../../../app/etc/local.xml'];
        expect($this->model->resolveStoredPath($value, 'quote_path'))->toBeNull();
    });

    it('rejects an order path that traverses with backslashes', function () {
        $value = ['order_path' => $this->orderRel . '\\..\\..\\..\\..\\app\\etc\\local.xml'];
        expect($this->model->resolveStoredPath($value, 'order_path'))->toBeNull();
    });

    it('rejects a path that points at a different directory of the install', function () {
        $value = ['quote_path' => '/app/etc/local.xml'];
        expect($this->model->resolveStoredPath($value, 'quote_path'))->toBeNull();
    });

    it('rejects an order path stored under the quote directory', function () {
        $value = ['order_path' => $this->quoteRel . '/a/b/abc123.txt'];
        expect($this->model->resolveStoredPath($value, 'order_path'))->toBeNull();
    });

    it('rejects a missing, empty or non-string value', function () {
        expect($this->model->resolveStoredPath([], 'quote_path'))->toBeNull();
        expect($this->model->resolveStoredPath(['quote_path' => ''], 'quote_path'))->toBeNull();
        expect($this->model->resolveStoredPath(['quote_path' => ['x']], 'quote_path'))->toBeNull();
    });
});

describe('Mage_Catalog_Model_Product_Option_Type_File::resolveStoragePath()', function () {
    beforeEach(function () {
        $this->model = Mage::getModel('catalog/product_option_type_file');
        $this->quoteRel = $this->model->getQuoteTargetDir(true);
        $this->orderRel = $this->model->getOrderTargetDir(true);
    });

    it('maps a stored quote path to the quote directory of the custom_options mount', function () {
        $value = ['quote_path' => $this->quoteRel . '/a/b/abc123.txt'];
        expect($this->model->resolveStoragePath($value, 'quote_path'))->toBe('quote/a/b/abc123.txt');
    });

    it('maps a stored order path to the order directory of the custom_options mount', function () {
        $value = ['order_path' => $this->orderRel . '/a/b/abc123.txt'];
        expect($this->model->resolveStoragePath($value, 'order_path'))->toBe('order/a/b/abc123.txt');
    });

    it('rejects a traversal, a foreign directory and a mismatched key', function () {
        expect($this->model->resolveStoragePath(['quote_path' => $this->quoteRel . '/../../etc/x'], 'quote_path'))->toBeNull();
        expect($this->model->resolveStoragePath(['quote_path' => '/app/etc/local.xml'], 'quote_path'))->toBeNull();
        expect($this->model->resolveStoragePath(['order_path' => $this->quoteRel . '/a/b/c.txt'], 'order_path'))->toBeNull();
        expect($this->model->resolveStoragePath(['quote_path' => $this->quoteRel], 'quote_path'))->toBeNull();
        expect($this->model->resolveStoragePath([], 'quote_path'))->toBeNull();
    });
});

describe('Mage_Catalog_Model_Product_Option_Type_File on the custom_options mount', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_custom_options_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new \Maho\Storage\Mount('custom_options', new \League\Flysystem\Local\LocalFilesystemAdapter($this->root), $this->root);
        \Maho\Storage\MountRegistry::register($this->mount);
        $this->mount->write('quote/a/b/h.txt', 'Q');
        $this->mount->write('order/a/b/h.txt', 'O');
        $this->model = Mage::getModel('catalog/product_option_type_file');
        $this->value = [
            'quote_path' => $this->model->getQuoteTargetDir(true) . '/a/b/h.txt',
            'order_path' => $this->model->getOrderTargetDir(true) . '/a/b/h.txt',
        ];
    });

    afterEach(function (): void {
        \Maho\Storage\MountRegistry::reset();
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    });

    it('opens the order copy of a stored file before the quote copy', function (): void {
        $file = $this->model->openStoredFile($this->value);

        expect($file)->not->toBeNull()
            ->and(stream_get_contents($file['stream']))->toBe('O')
            ->and($file['size'])->toBe(1);
    });

    it('copies the quote file to the order directory of the mount', function (): void {
        $this->mount->delete('order/a/b/h.txt');
        $option = Mage::getModel('sales/quote_item_option')->setValue(Mage::helper('core')->jsonEncode($this->value));
        $this->model->setConfigurationItemOption($option);

        $this->model->copyQuoteToOrder();

        expect($this->mount->read('order/a/b/h.txt'))->toBe('Q')
            ->and($this->mount->read('quote/a/b/h.txt'))->toBe('Q');
    });

    it('deletes the quote copy and keeps the order copy', function (): void {
        $this->model->deleteQuoteFile($this->value);

        expect($this->mount->fileExists('quote/a/b/h.txt'))->toBeFalse()
            ->and($this->mount->fileExists('order/a/b/h.txt'))->toBeTrue();
    });
});
