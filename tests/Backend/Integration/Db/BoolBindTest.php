<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Db\Ddl\Table;

uses(Tests\MahoBackendTestCase::class);

/**
 * The adapter binds a bool as 1 or 0. PDO binds false as an empty string, which MySQL
 * in strict mode and PostgreSQL reject for an integer column, and which SQLite stores as text.
 */

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->tableName = 'test_bool_bind_' . uniqid();

    $table = $this->adapter->newTable($this->tableName)
        ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
        ->addColumn('flag', Table::TYPE_SMALLINT, null, ['nullable' => false, 'default' => 0]);
    $this->adapter->createTable($table);
});

afterEach(function () {
    if ($this->adapter->isTableExists($this->tableName)) {
        $this->adapter->dropTable($this->tableName);
    }
});

it('stores a bool as 1 or 0 on insert and update', function () {
    $this->adapter->insert($this->tableName, ['id' => 1, 'flag' => false]);
    $this->adapter->insert($this->tableName, ['id' => 2, 'flag' => true]);
    $this->adapter->update($this->tableName, ['flag' => false], ['id = ?' => 2]);

    $flags = $this->adapter->fetchPairs(
        $this->adapter->select()->from($this->tableName, ['id', 'flag'])->order('id'),
    );

    expect(array_map(strval(...), $flags))->toBe([1 => '0', 2 => '0']);
});

it('matches a bound bool against an integer column', function () {
    $this->adapter->insert($this->tableName, ['id' => 1, 'flag' => 0]);

    $id = $this->adapter->fetchOne("SELECT id FROM {$this->tableName} WHERE flag = ?", [false]);

    expect((int) $id)->toBe(1);
});
