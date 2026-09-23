<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Db\Ddl\Table;

uses(Tests\MahoBackendTestCase::class);

/**
 * A DECIMAL column reads back as a string with its declared scale on every backend.
 *
 * MySQL and PostgreSQL do this natively. SQLite has no decimal type and used to return an int
 * or a float, so code that broke on the other two backends passed on SQLite.
 * Maho\Db\Driver\Sqlite\Middleware closes that gap, and these tests hold all three to it.
 */

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->tableName = 'test_decimal_' . uniqid();

    $table = $this->adapter->newTable($this->tableName)
        ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
        ->addColumn('price', Table::TYPE_DECIMAL, '12,4', ['nullable' => true])
        ->addColumn('whole', Table::TYPE_DECIMAL, '10,0', ['nullable' => true]);
    $this->adapter->createTable($table);

    $this->adapter->insertMultiple($this->tableName, [
        ['id' => 1, 'price' => 10.5, 'whole' => 3],
        ['id' => 2, 'price' => 2, 'whole' => 0],
        ['id' => 3, 'price' => null, 'whole' => null],
        ['id' => 4, 'price' => -0.25, 'whole' => -7],
    ]);
});

afterEach(function () {
    if ($this->adapter->isTableExists($this->tableName)) {
        $this->adapter->dropTable($this->tableName);
    }
});

it('returns decimal columns as strings with the declared scale', function () {
    $rows = $this->adapter->fetchAll(
        $this->adapter->select()->from($this->tableName, ['price', 'whole'])->order('id'),
    );

    expect($rows)->toBe([
        ['price' => '10.5000', 'whole' => '3'],
        ['price' => '2.0000', 'whole' => '0'],
        ['price' => null, 'whole' => null],
        ['price' => '-0.2500', 'whole' => '-7'],
    ]);
});

it('formats decimal values on every fetch method', function () {
    $select = fn(string ...$columns) => $this->adapter->select()
        ->from($this->tableName, $columns)
        ->order('id');

    expect($this->adapter->fetchOne($select('price')))->toBe('10.5000')
        ->and($this->adapter->fetchCol($select('price')))->toBe(['10.5000', '2.0000', null, '-0.2500'])
        ->and($this->adapter->fetchPairs($select('id', 'price')))->toBe([1 => '10.5000', 2 => '2.0000', 3 => null, 4 => '-0.2500'])
        ->and($this->adapter->fetchRow($select('price')))->toBe(['price' => '10.5000'])
        ->and($this->adapter->query($select('price'))->fetch())->toBe(['price' => '10.5000']);
});

it('formats a decimal column under an alias and leaves an integer column alone', function () {
    $row = $this->adapter->fetchRow(
        $this->adapter->select()
            ->from($this->tableName, ['amount' => 'price', 'id'])
            ->where('id = ?', 2),
    );

    expect($row['amount'])->toBe('2.0000')
        ->and((int) $row['id'])->toBe(2);
});
