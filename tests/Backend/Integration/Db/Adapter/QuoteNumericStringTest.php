<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Db\Ddl\Table;
use Maho\Db\Expr;

uses(Tests\MahoBackendTestCase::class);

/**
 * A string that holds digits stays text when the adapter quotes it, so a code with a leading zero
 * matches only itself. SQLite compares an INTEGER column with a quoted number by the column affinity.
 */

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->tableName = 'test_quote_numeric_' . uniqid();

    $table = $this->adapter->newTable($this->tableName)
        ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
        ->addColumn('code', Table::TYPE_TEXT, 32, ['nullable' => false]);
    $this->adapter->createTable($table);

    $this->adapter->insertMultiple($this->tableName, [
        ['id' => 1, 'code' => '02'],
        ['id' => 2, 'code' => '2'],
        ['id' => 3, 'code' => '007'],
        ['id' => 4, 'code' => '7'],
        ['id' => 5, 'code' => '1E5'],
        ['id' => 6, 'code' => '100000'],
    ]);
});

afterEach(function () {
    if ($this->adapter->isTableExists($this->tableName)) {
        $this->adapter->dropTable($this->tableName);
    }
});

function quoteNumericStringIds(object $adapter, Maho\Db\Select $select): array
{
    return array_map(intval(...), $adapter->fetchCol($select->order('id')));
}

it('quotes a string with a leading zero as text', function () {
    expect($this->adapter->quote('02'))->toBe("'02'")
        ->and($this->adapter->quote('007'))->toBe("'007'")
        ->and($this->adapter->quote('1E5'))->toBe("'1E5'")
        ->and($this->adapter->quote(['02', '007']))->toBe("'02', '007'");
});

it('keeps the quoting of an int, a float, a bool, null and an expression', function () {
    if (!$this->adapter instanceof Maho\Db\Adapter\Pdo\Sqlite) {
        $this->markTestSkipped('The quoted form of a value depends on the adapter. This test covers the SQLite adapter.');
    }
    expect($this->adapter->quote(2))->toBe('2')
        ->and($this->adapter->quote(-7))->toBe('-7')
        ->and(trim($this->adapter->quote(true), "'"))->toBe('1')
        ->and($this->adapter->quote(null))->toBe('NULL')
        ->and($this->adapter->quote(new Expr('COUNT(*)')))->toBe('COUNT(*)')
        ->and($this->adapter->quote([1, 2]))->toBe('1, 2')
        ->and((float) trim($this->adapter->quote(1.5), "'"))->toBe(1.5);
});

it('finds a code with a leading zero and not the number', function () {
    $select = $this->adapter->select()->from($this->tableName, ['id'])->where('code = ?', '02');
    expect(quoteNumericStringIds($this->adapter, $select))->toBe([1]);

    $select = $this->adapter->select()->from($this->tableName, ['id'])->where('code = ?', '007');
    expect(quoteNumericStringIds($this->adapter, $select))->toBe([3]);

    $select = $this->adapter->select()->from($this->tableName, ['id'])->where('code IN (?)', ['02', '007']);
    expect(quoteNumericStringIds($this->adapter, $select))->toBe([1, 3]);
});

it('finds a code with an exponent and not the number', function () {
    $select = $this->adapter->select()->from($this->tableName, ['id'])->where('code = ?', '1E5');

    expect(quoteNumericStringIds($this->adapter, $select))->toBe([5]);
});

it('finds a code with digits only', function () {
    $select = $this->adapter->select()->from($this->tableName, ['id'])->where('code = ?', '7');
    expect(quoteNumericStringIds($this->adapter, $select))->toBe([4]);

    if ($this->adapter instanceof Maho\Db\Adapter\Pdo\Sqlite) {
        // MySQL compares a text column with an integer as numbers, so there 7 also matches '007'
        $select = $this->adapter->select()->from($this->tableName, ['id'])->where('code = ?', 7);
        expect(quoteNumericStringIds($this->adapter, $select))->toBe([4]);
    }
});

it('matches an integer column with a numeric string', function () {
    $select = $this->adapter->select()->from($this->tableName, ['code'])->where('id = ?', '3');
    expect($this->adapter->fetchOne($select))->toBe('007');

    $select = $this->adapter->select()->from($this->tableName, ['id'])->where('id IN (?)', ['1', '04']);
    expect(quoteNumericStringIds($this->adapter, $select))->toBe([1, 4]);
});

it('matches an integer expression with a numeric string', function () {
    $condition = $this->adapter->getCheckSql('id > 0', 'id', '0');
    $select = $this->adapter->select()->from($this->tableName, ['code'])->where("{$condition} = ?", '3');
    expect($this->adapter->fetchOne($select))->toBe('007');

    $condition = $this->adapter->getIfNullSql('id', '0');
    $select = $this->adapter->select()->from($this->tableName, ['code'])->where("{$condition} = ?", '4');
    expect($this->adapter->fetchOne($select))->toBe('7');
});

it('loads a region by a code with a leading zero', function () {
    $region = Mage::getModel('directory/region')
        ->setCountryId('AD')
        ->setCode('02')
        ->setDefaultName('Canillo')
        ->save();

    try {
        expect((int) Mage::getModel('directory/region')->loadByCode('02', 'AD')->getId())->toBe((int) $region->getId())
            ->and(Mage::getModel('directory/region')->loadByCode('2', 'AD')->getId())->toBeNull();
    } finally {
        $region->delete();
    }
});
