<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Db\Ddl\Table;

uses(Tests\MahoBackendTestCase::class);

/**
 * PostgreSQL refuses a query that compares an integer column with a value that the column cannot hold.
 * The adapter tells if a column can hold a value, and a model load with such a value finds no row on every database.
 * SQLite stores each integer column in 64 bits, so only a value above 64 bits is out of range there.
 */

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->isSqlite = $this->adapter instanceof Maho\Db\Adapter\Pdo\Sqlite;
    $this->tableName = 'test_column_range_' . uniqid();

    $table = $this->adapter->newTable($this->tableName)
        ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
        ->addColumn('small', Table::TYPE_SMALLINT, null, ['nullable' => false, 'default' => 0])
        ->addColumn('big', Table::TYPE_BIGINT, null, ['nullable' => false, 'default' => 0])
        ->addColumn('code', Table::TYPE_TEXT, 32, ['nullable' => false, 'default' => '']);
    $this->adapter->createTable($table);
    $this->columns = $this->adapter->describeTable($this->tableName);
});

afterEach(function () {
    if ($this->adapter->isTableExists($this->tableName)) {
        $this->adapter->dropTable($this->tableName);
    }
});

describe('AdapterInterface::canColumnHoldValue()', function () {
    it('accepts a whole number in the range of the column', function (string $column, mixed $value) {
        expect($this->adapter->canColumnHoldValue($this->columns[$column], $value))->toBeTrue();
    })->with([
        'int' => ['id', 1],
        'largest INTEGER' => ['id', 2147483647],
        'largest SMALLINT' => ['small', 32767],
        'smallest SMALLINT' => ['small', -32768],
        'largest BIGINT' => ['big', PHP_INT_MAX],
        'largest BIGINT as text' => ['big', '9223372036854775807'],
        'text of digits' => ['id', '42'],
        'text with a leading zero' => ['id', '007'],
        'text with spaces' => ['id', ' 42 '],
        'negative text' => ['small', '-5'],
        'whole float' => ['id', 5.0],
        'bool' => ['small', true],
        'null' => ['id', null],
    ]);

    it('refuses a number out of the range of the column', function (string $column, mixed $value) {
        if ($this->isSqlite) {
            $this->markTestSkipped('An integer column of SQLite holds 64 bits.');
        }
        expect($this->adapter->canColumnHoldValue($this->columns[$column], $value))->toBeFalse();
    })->with([
        'above INTEGER' => ['id', 2147483648],
        'far above INTEGER' => ['id', 99999999999],
        'far above INTEGER as text' => ['id', '99999999999'],
        'below INTEGER' => ['id', -2147483649],
        'above unsigned SMALLINT' => ['small', 70000],
        'above unsigned SMALLINT as text' => ['small', '70000'],
        'below SMALLINT' => ['small', -70000],
    ]);

    it('refuses a value that no integer column holds', function (string $column, mixed $value) {
        expect($this->adapter->canColumnHoldValue($this->columns[$column], $value))->toBeFalse();
    })->with([
        'text' => ['id', 'abc'],
        'number then text' => ['id', '12abc'],
        'empty text' => ['id', ''],
        'decimal text' => ['id', '5.0'],
        'float with a fraction' => ['small', 5.5],
        'text above BIGINT' => ['big', '99999999999999999999999'],
        'float above BIGINT' => ['big', 1.0E+30],
        'text above 64 bits in INTEGER' => ['id', '99999999999999999999999'],
    ]);

    it('accepts any value for a column that is not an integer column', function () {
        expect($this->adapter->canColumnHoldValue($this->columns['code'], 'abc'))->toBeTrue()
            ->and($this->adapter->canColumnHoldValue($this->columns['code'], 99999999999))->toBeTrue()
            ->and($this->adapter->canColumnHoldValue([], 'abc'))->toBeTrue();
    });

    it('accepts 64 bits in each integer column on SQLite', function () {
        if (!$this->isSqlite) {
            $this->markTestSkipped('This test covers the SQLite adapter.');
        }
        expect($this->adapter->canColumnHoldValue($this->columns['small'], 99999999999))->toBeTrue()
            ->and($this->adapter->canColumnHoldValue($this->columns['id'], PHP_INT_MAX))->toBeTrue();
    });
});

describe('a model load with an ID that the column cannot hold', function () {
    it('gives an empty model for a flat table', function (string $alias, mixed $id, ?string $field) {
        $model = Mage::getModel($alias)->load($id, $field);

        expect($model->getId())->toBeNull();
    })->with([
        'INTEGER ID' => ['core/url_rewrite', 99999999999, null],
        'INTEGER ID as text' => ['core/url_rewrite', '99999999999', null],
        'ID above 64 bits' => ['core/url_rewrite', '99999999999999999999999', null],
        'SMALLINT ID' => ['cms/block', 70000, null],
        'store view ID' => ['core/store', 70000, null],
        'website ID' => ['core/website', 70000, null],
        'customer group ID' => ['customer/group', 70000, null],
        'attribute ID' => ['eav/entity_attribute', 70000, null],
        'SMALLINT field that is not the ID' => ['core/store', 70000, 'website_id'],
    ]);

    it('gives an empty model for an EAV entity', function (string $alias, mixed $id) {
        $model = Mage::getModel($alias)->load($id);

        expect($model->getId())->toBeNull();
    })->with([
        'product, number out of range' => ['catalog/product', 99999999999],
        'product, number out of range as text' => ['catalog/product', '99999999999'],
        'product, text' => ['catalog/product', 'abc'],
        'product, number then text' => ['catalog/product', '12abc'],
        'category' => ['catalog/category', 99999999999],
        'customer' => ['customer/customer', 99999999999],
        'customer address' => ['customer/address', 99999999999],
    ]);

    it('still loads a row by an ID in range, as an int or as text', function () {
        $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
        $productId = (int) Mage::getResourceModel('catalog/product_collection')->getFirstItem()->getId();

        expect((int) Mage::getModel('core/store')->load($storeId)->getId())->toBe($storeId)
            ->and((int) Mage::getModel('core/store')->load((string) $storeId)->getId())->toBe($storeId)
            ->and($productId)->toBeGreaterThan(0)
            ->and((int) Mage::getModel('catalog/product')->load($productId)->getId())->toBe($productId)
            ->and((int) Mage::getModel('catalog/product')->load((string) $productId)->getId())->toBe($productId);
    });
});
