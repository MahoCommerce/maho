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
