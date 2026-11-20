<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Db\Adapter\AdapterInterface;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->sourceTable = $this->adapter->getTableName('core_config_data');

    // Create temporary test tables using DDL API (works with both MySQL and PostgreSQL)
    $this->tempTableSource = 'test_source_' . uniqid();
    $this->tempTableTarget = 'test_target_' . uniqid();

    $sourceTable = $this->adapter->newTable($this->tempTableSource)
        ->addColumn('id', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity' => true,
            'nullable' => false,
            'primary' => true,
        ], 'ID')
        ->addColumn('name', \Maho\Db\Ddl\Table::TYPE_TEXT, 100, [], 'Name')
        ->addColumn('value', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [], 'Value')
        ->addColumn('status', \Maho\Db\Ddl\Table::TYPE_SMALLINT, null, ['default' => 1], 'Status');
    $this->adapter->createTable($sourceTable);

    $targetTable = $this->adapter->newTable($this->tempTableTarget)
        ->addColumn('id', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity' => true,
            'nullable' => false,
            'primary' => true,
        ], 'ID')
        ->addColumn('name', \Maho\Db\Ddl\Table::TYPE_TEXT, 100, [], 'Name')
        ->addColumn('value', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [], 'Value')
        ->addColumn('status', \Maho\Db\Ddl\Table::TYPE_SMALLINT, null, ['default' => 1], 'Status')
        ->addIndex(
            $this->adapter->getIndexName($this->tempTableTarget, ['name'], AdapterInterface::INDEX_TYPE_UNIQUE),
            ['name'],
            ['type' => AdapterInterface::INDEX_TYPE_UNIQUE],
        );
    $this->adapter->createTable($targetTable);

    // Insert test data
    $this->adapter->insert($this->tempTableSource, ['name' => 'test1', 'value' => 100]);
    $this->adapter->insert($this->tempTableSource, ['name' => 'test2', 'value' => 200]);
    $this->adapter->insert($this->tempTableSource, ['name' => 'test3', 'value' => 300]);
});

afterEach(function () {
    $this->adapter->dropTable($this->tempTableSource);
    $this->adapter->dropTable($this->tempTableTarget);
});

describe('insertFromSelect', function () {
    it('inserts data from select query', function () {
        $select = $this->adapter->select()
            ->from($this->tempTableSource, ['name', 'value'])
            ->where('value > ?', 100);

        $sql = $this->adapter->insertFromSelect(
            $select,
            $this->tempTableTarget,
            ['name', 'value'],
        );

        $this->adapter->query($sql);

        $count = $this->adapter->fetchOne("SELECT COUNT(*) FROM {$this->tempTableTarget}");
        expect((int) $count)->toBe(2); // test2 and test3

        $result = $this->adapter->fetchAll("SELECT name, value FROM {$this->tempTableTarget} ORDER BY value");
        expect($result[0]['name'])->toBe('test2');
        expect((int) $result[0]['value'])->toBe(200);
        expect($result[1]['name'])->toBe('test3');
        expect((int) $result[1]['value'])->toBe(300);
    });

    it('handles INSERT ON DUPLICATE UPDATE correctly', function () {
        // PostgreSQL's ON CONFLICT uses primary key columns, while MySQL's ON DUPLICATE KEY
        // triggers on any unique constraint. This test uses insertOnDuplicate which handles
        // this correctly for both databases.
        $this->adapter->insertOnDuplicate(
            $this->tempTableTarget,
            ['name' => 'test1', 'value' => 100],
            ['value'],
        );

        // Insert duplicate with updated value
        $this->adapter->insertOnDuplicate(
            $this->tempTableTarget,
            ['name' => 'test1', 'value' => 200],
            ['value'],
        );

        // Should have updated, not created duplicate
        $count = $this->adapter->fetchOne("SELECT COUNT(*) FROM {$this->tempTableTarget}");
        expect((int) $count)->toBe(1);

        $value = $this->adapter->fetchOne(
            "SELECT value FROM {$this->tempTableTarget} WHERE name = ?",
            ['test1'],
        );
        expect((int) $value)->toBe(200);
    });

    it('handles INSERT IGNORE correctly', function () {
        // Insert initial data
        $this->adapter->insert($this->tempTableTarget, ['name' => 'test1', 'value' => 100]);

        // Try to insert duplicate with INSERT IGNORE
        $select = $this->adapter->select()
            ->from($this->tempTableSource, ['name', 'value'])
            ->where('name = ?', 'test1');

        $sql = $select->insertIgnoreFromSelect(
            $this->tempTableTarget,
            ['name', 'value'],
        );

        $this->adapter->query($sql);

        // Should have ignored, not error
        $count = $this->adapter->fetchOne("SELECT COUNT(*) FROM {$this->tempTableTarget}");
        expect((int) $count)->toBe(1);
    });
});

describe('updateFromSelect', function () {
    it('generates update from select SQL', function () {
        // Test that the method generates proper SQL structure
        $select = $this->adapter->select()
            ->from(['t' => $this->tempTableTarget])
            ->join(
                ['s' => $this->tempTableSource],
                't.name = s.name',
                [],
            )
            ->where('s.value > ?', 100);

        $sql = $this->adapter->updateFromSelect(
            $select,
            ['t' => $this->tempTableTarget],
        );

        // Verify SQL structure contains expected elements
        expect($sql)->toContain($this->tempTableTarget);
        expect($sql)->toContain($this->tempTableSource);
        expect($sql)->toBeString();
    });

    it('handles cross-table updates with WHERE conditions', function () {
        $select = $this->adapter->select()
            ->from(['t' => $this->tempTableTarget])
            ->join(
                ['s' => $this->tempTableSource],
                't.name = s.name',
                [],
            )
            ->where('s.status = ?', 1);

        $sql = $this->adapter->updateFromSelect($select, ['t' => $this->tempTableTarget]);

        expect($sql)->toBeString();
        expect($sql)->toContain('UPDATE');
    });

    describe('with rows', function () {
        beforeEach(function () {
            foreach (['test1', 'test2', 'test3'] as $index => $name) {
                $this->adapter->insert($this->tempTableTarget, ['name' => $name, 'value' => 0]);
                $this->adapter->update($this->tempTableSource, ['status' => $index + 1], ['name = ?' => $name]);
            }
            $this->targetValues = fn() => $this->adapter->fetchPairs(
                $this->adapter->select()->from($this->tempTableTarget, ['name', 'value'])->order('name'),
            );
        });

        it('applies the WHERE conditions to every row that a join condition with OR matches', function () {
            $select = $this->adapter->select()
                ->join(['s' => $this->tempTableSource], 't.name = s.name OR t.value = s.value', ['value' => 'value'])
                ->where('s.status = ?', 1);

            $this->adapter->query($this->adapter->updateFromSelect($select, ['t' => $this->tempTableTarget]));

            expect(array_map('intval', ($this->targetValues)()))->toBe(['test1' => 100, 'test2' => 0, 'test3' => 0]);
        });

        it('keeps an orWhere() condition after the join conditions', function () {
            $select = $this->adapter->select()
                ->join(['s' => $this->tempTableSource], 't.name = s.name', ['value' => 'value'])
                ->where('s.status = ?', 1)
                ->orWhere('s.status = ?', 3);

            $this->adapter->query($this->adapter->updateFromSelect($select, ['t' => $this->tempTableTarget]));

            expect(array_map('intval', ($this->targetValues)()))->toBe(['test1' => 100, 'test2' => 0, 'test3' => 300]);
        });
    });
});

describe('deleteFromSelect', function () {
    it('deletes rows based on select query', function () {
        // Insert target data
        $this->adapter->insert($this->tempTableTarget, ['name' => 'test1', 'value' => 100]);
        $this->adapter->insert($this->tempTableTarget, ['name' => 'test2', 'value' => 200]);
        $this->adapter->insert($this->tempTableTarget, ['name' => 'test3', 'value' => 300]);

        $initialCount = $this->adapter->fetchOne("SELECT COUNT(*) FROM {$this->tempTableTarget}");
        expect((int) $initialCount)->toBe(3);

        // Delete using select
        $select = $this->adapter->select()
            ->from($this->tempTableTarget, ['id'])
            ->where('value > ?', 150);

        $sql = $select->deleteFromSelect($this->tempTableTarget);
        $this->adapter->query($sql);

        $finalCount = $this->adapter->fetchOne("SELECT COUNT(*) FROM {$this->tempTableTarget}");
        expect((int) $finalCount)->toBe(1); // Only test1 remains

        $remaining = $this->adapter->fetchOne(
            "SELECT name FROM {$this->tempTableTarget}",
        );
        expect($remaining)->toBe('test1');
    });

    it('deletes with JOIN conditions', function () {
        $this->adapter->insert($this->tempTableTarget, ['name' => 'test1', 'value' => 100]);
        $this->adapter->insert($this->tempTableTarget, ['name' => 'test4', 'value' => 400]);

        $select = $this->adapter->select()
            ->from(['t' => $this->tempTableTarget])
            ->joinInner(
                ['s' => $this->tempTableSource],
                't.name = s.name',
                [],
            )
            ->where('s.value >= ?', 100);

        $sql = $select->deleteFromSelect('t');

        // Should delete only matching rows
        expect($sql)->toContain('DELETE');
        expect($sql)->toBeString();
    });
});

describe('insertOnDuplicate', function () {
    it('inserts new row when no duplicate exists', function () {
        $data = [
            'name' => 'new_item',
            'value' => 999,
        ];

        $affected = $this->adapter->insertOnDuplicate(
            $this->tempTableTarget,
            $data,
            ['value'],
        );

        expect($affected)->toBeGreaterThan(0);

        $result = $this->adapter->fetchRow(
            "SELECT * FROM {$this->tempTableTarget} WHERE name = ?",
            ['new_item'],
        );

        expect($result['name'])->toBe('new_item');
        expect((int) $result['value'])->toBe(999);
    });

    it('updates existing row on duplicate key', function () {
        $this->adapter->insert($this->tempTableTarget, ['name' => 'duplicate_test', 'value' => 100]);

        $data = [
            'name' => 'duplicate_test',
            'value' => 200,
        ];

        $affected = $this->adapter->insertOnDuplicate(
            $this->tempTableTarget,
            $data,
            ['value'],
        );

        // MySQL returns 2 for updated rows
        expect($affected)->toBeGreaterThan(0);

        $result = $this->adapter->fetchOne(
            "SELECT value FROM {$this->tempTableTarget} WHERE name = ?",
            ['duplicate_test'],
        );

        expect((int) $result)->toBe(200);
    });

    it('inserts multiple rows at once', function () {
        $data = [
            ['name' => 'bulk1', 'value' => 11],
            ['name' => 'bulk2', 'value' => 22],
            ['name' => 'bulk3', 'value' => 33],
        ];

        $affected = $this->adapter->insertOnDuplicate(
            $this->tempTableTarget,
            $data,
            ['value'],
        );

        expect($affected)->toBeGreaterThan(0);

        $count = $this->adapter->fetchOne(
            "SELECT COUNT(*) FROM {$this->tempTableTarget} WHERE name LIKE 'bulk%'",
        );

        expect((int) $count)->toBe(3);
    });

    it('writes each value into its column when the rows list their keys in different orders', function () {
        $this->adapter->insertOnDuplicate(
            $this->tempTableTarget,
            [
                ['name' => 'order1', 'value' => 1],
                ['value' => 2, 'name' => 'order2'],
            ],
            ['value'],
        );

        $values = $this->adapter->fetchPairs(
            "SELECT name, value FROM {$this->tempTableTarget} WHERE name LIKE 'order%' ORDER BY name",
        );

        expect(array_map(intval(...), $values))->toBe(['order1' => 1, 'order2' => 2]);
    });

    it('rejects a row that does not have the same columns as the first row', function (array $secondRow) {
        expect(fn() => $this->adapter->insertOnDuplicate(
            $this->tempTableTarget,
            [['name' => 'keys1', 'value' => 1], $secondRow],
            ['value'],
        ))->toThrow(\Maho\Db\Exception::class, 'Invalid data for insert');
    })->with([
        'a missing column' => [['name' => 'keys2']],
        'an extra column' => [['name' => 'keys2', 'value' => 2, 'status' => 0]],
    ]);

    it('rejects empty data without a write', function () {
        expect(fn() => $this->adapter->insertOnDuplicate($this->tempTableTarget, [], ['value']))
            ->toThrow(\Maho\Db\Exception::class, 'Invalid data for insert');

        expect((int) $this->adapter->fetchOne("SELECT COUNT(*) FROM {$this->tempTableTarget}"))->toBe(0);
    });

    it('applies two rows with the same key in order, so the last row wins', function () {
        $this->adapter->insertOnDuplicate(
            $this->tempTableTarget,
            [
                ['name' => 'repeat', 'value' => 1],
                ['name' => 'other', 'value' => 5],
                ['name' => 'repeat', 'value' => 2],
            ],
            ['value'],
        );

        $values = $this->adapter->fetchPairs(
            "SELECT name, value FROM {$this->tempTableTarget} WHERE name IN ('repeat', 'other') ORDER BY name",
        );

        expect(array_map(intval(...), $values))->toBe(['other' => 5, 'repeat' => 2]);
    });

    it('applies two rows in order when their keys differ as text but are the same number', function () {
        $table = 'test_numeric_key_' . uniqid();
        $this->adapter->createTable(
            $this->adapter->newTable($table)
                ->addColumn('id', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true], 'ID')
                ->addColumn('value', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [], 'Value'),
        );

        try {
            $this->adapter->insertOnDuplicate(
                $table,
                [
                    ['id' => 1, 'value' => 1],
                    ['id' => '01', 'value' => 2],
                ],
                ['value'],
            );

            $values = $this->adapter->fetchPairs("SELECT id, value FROM {$table}");

            expect(array_map(intval(...), $values))->toBe([1 => 2]);
        } finally {
            $this->adapter->dropTable($table);
        }
    });

    it('keeps the bytes of a binary column when an expression in the same row takes no bind position', function () {
        $table = 'test_binary_' . uniqid();
        $this->adapter->createTable(
            $this->adapter->newTable($table)
                ->addColumn('code', \Maho\Db\Ddl\Table::TYPE_TEXT, 32, ['nullable' => false, 'primary' => true], 'Code')
                ->addColumn('value', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [], 'Value')
                ->addColumn('data', \Maho\Db\Ddl\Table::TYPE_VARBINARY, 16, [], 'Data'),
        );

        try {
            $bytes = ['a' => "\x80\xff\x01", 'b' => "\xfe\x7f\xc3", 'c' => "\x90\xa0\xb0"];
            $this->adapter->insertOnDuplicate(
                $table,
                [
                    ['code' => 'a', 'value' => new \Maho\Db\Expr('1 + 1'), 'data' => $bytes['a']],
                    ['code' => 'b', 'value' => 3, 'data' => $bytes['b']],
                    ['code' => 'c', 'value' => new \Maho\Db\Expr('2 + 2'), 'data' => $bytes['c']],
                ],
                ['value', 'data'],
            );

            $stored = [];
            foreach ($this->adapter->fetchAll("SELECT code, data FROM {$table} ORDER BY code") as $row) {
                $stored[$row['code']] = is_resource($row['data']) ? stream_get_contents($row['data']) : $row['data'];
            }

            expect($stored)->toBe($bytes);
        } finally {
            $this->adapter->dropTable($table);
        }
    });
});

describe('insertMultiple', function () {
    it('inserts multiple rows efficiently', function () {
        $data = [
            ['name' => 'multi1', 'value' => 111],
            ['name' => 'multi2', 'value' => 222],
            ['name' => 'multi3', 'value' => 333],
        ];

        $affected = $this->adapter->insertMultiple($this->tempTableTarget, $data);

        expect($affected)->toBe(3);

        $results = $this->adapter->fetchAll(
            "SELECT name, value FROM {$this->tempTableTarget} WHERE name LIKE 'multi%' ORDER BY value",
        );

        expect(count($results))->toBe(3);
        expect($results[0]['name'])->toBe('multi1');
        expect((int) $results[0]['value'])->toBe(111);
    });
});
