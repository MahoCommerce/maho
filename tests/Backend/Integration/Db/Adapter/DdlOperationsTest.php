<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Db\Adapter\AdapterInterface;
use Maho\Db\Ddl\Table;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->testTableName = 'test_ddl_' . uniqid();
});

afterEach(function () {
    // Cleanup any test tables
    if ($this->adapter->isTableExists($this->testTableName)) {
        $this->adapter->dropTable($this->testTableName);
    }
});

describe('DDL Operations - Table Creation', function () {
    it('creates table with basic column types', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, [
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary' => true,
            ], 'ID')
            ->addColumn('name', Table::TYPE_TEXT, 255, [
                'nullable' => false,
            ], 'Name')
            ->addColumn('description', Table::TYPE_TEXT, '64k', [
                'nullable' => true,
            ], 'Description')
            ->addColumn('price', Table::TYPE_DECIMAL, '12,4', [
                'nullable' => false,
                'default' => '0.0000',
            ], 'Price')
            ->addColumn('is_active', Table::TYPE_SMALLINT, null, [
                'nullable' => false,
                'default' => '1',
            ], 'Is Active')
            ->addColumn('created_at', Table::TYPE_DATETIME, null, [
                'nullable' => false,
                'default' => Table::TIMESTAMP_INIT,
            ], 'Created At')
            ->setComment('Test DDL Table');

        $this->adapter->createTable($table);

        expect($this->adapter->isTableExists($this->testTableName))->toBeTrue();
    });

    it('creates table with all column types', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['identity' => true, 'nullable' => false, 'primary' => true])
            ->addColumn('col_smallint', Table::TYPE_SMALLINT, null, [])
            ->addColumn('col_integer', Table::TYPE_INTEGER, null, [])
            ->addColumn('col_bigint', Table::TYPE_BIGINT, null, [])
            ->addColumn('col_float', Table::TYPE_FLOAT, null, [])
            ->addColumn('col_numeric', Table::TYPE_DECIMAL, '10,2', [])
            ->addColumn('col_text', Table::TYPE_TEXT, 255, [])
            ->addColumn('col_blob', Table::TYPE_BLOB, '2M', [])
            ->addColumn('col_datetime', Table::TYPE_DATETIME, null, [])
            ->addColumn('col_timestamp', Table::TYPE_TIMESTAMP, null, [])
            ->addColumn('col_date', Table::TYPE_DATE, null, [])
            ->addColumn('col_time', Table::TYPE_TIME, null, [])
            ->addColumn('col_boolean', Table::TYPE_BOOLEAN, null, []);

        $this->adapter->createTable($table);

        expect($this->adapter->isTableExists($this->testTableName))->toBeTrue();

        $describe = $this->adapter->describeTable($this->testTableName);
        expect($describe)->toHaveKey('id');
        expect($describe)->toHaveKey('col_text');
        expect($describe)->toHaveKey('col_blob');
    });

    it('creates table with indexes', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, [
                'identity' => true,
                'nullable' => false,
                'primary' => true,
            ])
            ->addColumn('email', Table::TYPE_TEXT, 255, ['nullable' => false])
            ->addColumn('name', Table::TYPE_TEXT, 255, ['nullable' => false])
            ->addColumn('status', Table::TYPE_SMALLINT, null, ['nullable' => false])
            ->addIndex(
                $this->adapter->getIndexName($this->testTableName, ['email'], AdapterInterface::INDEX_TYPE_UNIQUE),
                ['email'],
                ['type' => AdapterInterface::INDEX_TYPE_UNIQUE],
            )
            ->addIndex(
                $this->adapter->getIndexName($this->testTableName, ['name', 'status']),
                ['name', 'status'],
            );

        $this->adapter->createTable($table);

        $indexes = $this->adapter->getIndexList($this->testTableName);
        expect($indexes)->toBeArray();
        expect(count($indexes))->toBeGreaterThan(1); // PRIMARY + at least one custom index
    });

    it('creates table with foreign key', function () {
        // Create parent table first
        $parentTable = 'test_parent_' . uniqid();
        $table = $this->adapter->newTable($parentTable)
            ->addColumn('id', Table::TYPE_INTEGER, null, [
                'identity' => true,
                'nullable' => false,
                'primary' => true,
            ])
            ->addColumn('name', Table::TYPE_TEXT, 255, []);

        $this->adapter->createTable($table);

        // Create child table with FK
        $childTable = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, [
                'identity' => true,
                'nullable' => false,
                'primary' => true,
            ])
            ->addColumn('parent_id', Table::TYPE_INTEGER, null, ['nullable' => false])
            ->addIndex(
                $this->adapter->getIndexName($this->testTableName, ['parent_id']),
                ['parent_id'],
            )
            ->addForeignKey(
                $this->adapter->getForeignKeyName($this->testTableName, 'parent_id', $parentTable, 'id'),
                'parent_id',
                $parentTable,
                'id',
                Table::ACTION_CASCADE,
            );

        $this->adapter->createTable($childTable);

        expect($this->adapter->isTableExists($this->testTableName))->toBeTrue();

        $fks = $this->adapter->getForeignKeys($this->testTableName);
        expect($fks)->toBeArray();
        expect(count($fks))->toBeGreaterThan(0);

        // Cleanup
        $this->adapter->dropTable($this->testTableName);
        $this->adapter->dropTable($parentTable);
    });
});

describe('DDL Operations - Table Introspection', function () {
    it('checks if table exists', function () {
        expect($this->adapter->isTableExists($this->testTableName))->toBeFalse();

        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true]);

        $this->adapter->createTable($table);

        expect($this->adapter->isTableExists($this->testTableName))->toBeTrue();
    });

    it('describes table structure correctly', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, [
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary' => true,
            ])
            ->addColumn('email', Table::TYPE_TEXT, 255, [
                'nullable' => false,
            ])
            ->addColumn('age', Table::TYPE_SMALLINT, null, [
                'nullable' => true,
                'default' => null,
            ]);

        $this->adapter->createTable($table);

        $describe = $this->adapter->describeTable($this->testTableName);

        expect($describe)->toBeArray();
        expect($describe)->toHaveKey('id');
        expect($describe)->toHaveKey('email');
        expect($describe)->toHaveKey('age');

        expect($describe['id']['NULLABLE'])->toBeFalse();
        expect($describe['email']['NULLABLE'])->toBeFalse();
        expect($describe['age']['NULLABLE'])->toBeTrue();
    });

    it('checks if column exists in table', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
            ->addColumn('name', Table::TYPE_TEXT, 255, []);

        $this->adapter->createTable($table);

        expect($this->adapter->tableColumnExists($this->testTableName, 'id'))->toBeTrue();
        expect($this->adapter->tableColumnExists($this->testTableName, 'name'))->toBeTrue();
        expect($this->adapter->tableColumnExists($this->testTableName, 'nonexistent'))->toBeFalse();
    });

    it('gets table creation SQL from existing table', function () {
        // Use existing core_config_data table
        $configTable = $this->adapter->getTableName('core_config_data');

        $newTable = $this->adapter->createTableByDdl($configTable, $this->testTableName);

        expect($newTable)->toBeInstanceOf(Table::class);
        expect($newTable->getName())->toBe($this->testTableName);
    });
});

describe('DDL Operations - Column Management', function () {
    it('adds column to existing table', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
            ->addColumn('name', Table::TYPE_TEXT, 255, []);

        $this->adapter->createTable($table);

        expect($this->adapter->tableColumnExists($this->testTableName, 'email'))->toBeFalse();

        $this->adapter->addColumn($this->testTableName, 'email', [
            'type' => Table::TYPE_TEXT,
            'length' => 255,
            'nullable' => true,
            'comment' => 'Email Address',
        ]);

        expect($this->adapter->tableColumnExists($this->testTableName, 'email'))->toBeTrue();

        $describe = $this->adapter->describeTable($this->testTableName);
        expect($describe['email']['NULLABLE'])->toBeTrue();
    });

    it('modifies existing column', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
            ->addColumn('name', Table::TYPE_TEXT, 100, ['nullable' => true]);

        $this->adapter->createTable($table);

        // Modify column to be NOT NULL with larger length
        $this->adapter->modifyColumn($this->testTableName, 'name', [
            'type' => Table::TYPE_TEXT,
            'length' => 255,
            'nullable' => false,
            'default' => 'Unknown',
        ]);

        $describe = $this->adapter->describeTable($this->testTableName);
        expect($describe['name']['NULLABLE'])->toBeFalse();
    });

    it('changes column name and definition', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
            ->addColumn('old_name', Table::TYPE_TEXT, 255, []);

        $this->adapter->createTable($table);

        expect($this->adapter->tableColumnExists($this->testTableName, 'old_name'))->toBeTrue();
        expect($this->adapter->tableColumnExists($this->testTableName, 'new_name'))->toBeFalse();

        $this->adapter->changeColumn($this->testTableName, 'old_name', 'new_name', [
            'type' => Table::TYPE_TEXT,
            'length' => 255,
            'nullable' => false,
        ]);

        expect($this->adapter->tableColumnExists($this->testTableName, 'old_name'))->toBeFalse();
        expect($this->adapter->tableColumnExists($this->testTableName, 'new_name'))->toBeTrue();
    });

    it('drops column from table', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
            ->addColumn('name', Table::TYPE_TEXT, 255, [])
            ->addColumn('temp_column', Table::TYPE_TEXT, 255, []);

        $this->adapter->createTable($table);

        expect($this->adapter->tableColumnExists($this->testTableName, 'temp_column'))->toBeTrue();

        $this->adapter->dropColumn($this->testTableName, 'temp_column');

        expect($this->adapter->tableColumnExists($this->testTableName, 'temp_column'))->toBeFalse();
    });
});

describe('DDL Operations - modifyColumn (surgical)', function () {
    // A partial $definition passed to modifyColumn() must only touch the attributes the
    // caller specified; every other attribute (type, length, nullability, default,
    // comment) must round-trip unchanged. These tests build a column with all attributes
    // populated, snapshot describeTable() before, apply a partial modifyColumn, then
    // assert that only the targeted attribute changed.

    $createBaseTable = function ($adapter, $tableName) {
        $table = $adapter->newTable($tableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true], 'ID')
            ->addColumn('label', Table::TYPE_TEXT, 64, [
                'nullable' => false,
                'default' => 'pending',
            ], 'Label');
        $adapter->createTable($table);
    };

    it('changes only DEFAULT and preserves type, length, nullable', function () use ($createBaseTable) {
        $createBaseTable($this->adapter, $this->testTableName);
        $before = $this->adapter->describeTable($this->testTableName)['label'];

        $this->adapter->modifyColumn($this->testTableName, 'label', ['default' => 'queued']);

        $after = $this->adapter->describeTable($this->testTableName)['label'];
        expect($after['DEFAULT'])->toBe('queued');
        expect($after['DATA_TYPE'])->toBe($before['DATA_TYPE']);
        expect($after['LENGTH'])->toBe($before['LENGTH']);
        expect($after['NULLABLE'])->toBe($before['NULLABLE']);
    });

    it('changes only NULLABLE and preserves type, length, default', function () use ($createBaseTable) {
        $createBaseTable($this->adapter, $this->testTableName);
        $before = $this->adapter->describeTable($this->testTableName)['label'];

        $this->adapter->modifyColumn($this->testTableName, 'label', ['nullable' => true]);

        $after = $this->adapter->describeTable($this->testTableName)['label'];
        expect($after['NULLABLE'])->toBeTrue();
        expect($after['DATA_TYPE'])->toBe($before['DATA_TYPE']);
        expect($after['LENGTH'])->toBe($before['LENGTH']);
        expect($after['DEFAULT'])->toBe($before['DEFAULT']);
    });

    it('changes only COMMENT and preserves type, length, nullable, default', function () use ($createBaseTable) {
        $createBaseTable($this->adapter, $this->testTableName);
        $before = $this->adapter->describeTable($this->testTableName)['label'];

        $this->adapter->modifyColumn($this->testTableName, 'label', ['comment' => 'New label']);

        $after = $this->adapter->describeTable($this->testTableName)['label'];
        expect($after['DATA_TYPE'])->toBe($before['DATA_TYPE']);
        expect($after['LENGTH'])->toBe($before['LENGTH']);
        expect($after['NULLABLE'])->toBe($before['NULLABLE']);
        expect($after['DEFAULT'])->toBe($before['DEFAULT']);

        // MySQL exposes COLUMN_COMMENT via INFORMATION_SCHEMA; verify the comment
        // actually changed there.
        if ($this->adapter instanceof \Maho\Db\Adapter\Pdo\Mysql) {
            $comment = $this->adapter->raw_fetchRow(sprintf(
                "SELECT COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'label'",
                $this->adapter->quote($this->testTableName),
            ), 'COLUMN_COMMENT');
            expect($comment)->toBe('New label');
        }
    });

    it('widens column with combined NULLABLE+DEFAULT change', function () use ($createBaseTable) {
        $createBaseTable($this->adapter, $this->testTableName);
        $before = $this->adapter->describeTable($this->testTableName)['label'];

        $this->adapter->modifyColumn($this->testTableName, 'label', [
            'nullable' => true,
            'default' => null,
        ]);

        $after = $this->adapter->describeTable($this->testTableName)['label'];
        expect($after['NULLABLE'])->toBeTrue();
        expect($after['DEFAULT'])->toBeNull();
        expect($after['DATA_TYPE'])->toBe($before['DATA_TYPE']);
        expect($after['LENGTH'])->toBe($before['LENGTH']);
    });

    it('preserves TIMESTAMP CURRENT_TIMESTAMP default through unrelated change', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true], 'ID')
            ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, [
                'nullable' => false,
                'default' => Table::TIMESTAMP_INIT,
            ], 'Created At');
        $this->adapter->createTable($table);

        $before = $this->adapter->describeTable($this->testTableName)['created_at'];

        // No-op-ish change: just rewrite NULLABLE to its existing value. Should preserve
        // the CURRENT_TIMESTAMP default.
        $this->adapter->modifyColumn($this->testTableName, 'created_at', ['nullable' => false]);

        $after = $this->adapter->describeTable($this->testTableName)['created_at'];
        expect($after['NULLABLE'])->toBe($before['NULLABLE']);
        expect($after['DATA_TYPE'])->toBe($before['DATA_TYPE']);
        // DEFAULT must round-trip identically — losing CURRENT_TIMESTAMP would make the
        // column non-functional on MySQL.
        expect($after['DEFAULT'])->toBe($before['DEFAULT']);
    });

    it('preserves DECIMAL precision and scale through comment-only change', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true], 'ID')
            ->addColumn('amount', Table::TYPE_DECIMAL, '12,4', [
                'nullable' => false,
                'default' => '0.0000',
            ], 'Amount');
        $this->adapter->createTable($table);

        $before = $this->adapter->describeTable($this->testTableName)['amount'];

        $this->adapter->modifyColumn($this->testTableName, 'amount', ['comment' => 'New amount']);

        $after = $this->adapter->describeTable($this->testTableName)['amount'];
        expect($after['PRECISION'])->toBe($before['PRECISION']);
        expect($after['SCALE'])->toBe($before['SCALE']);
        expect($after['NULLABLE'])->toBe($before['NULLABLE']);
        expect((string) $after['DEFAULT'])->toBe((string) $before['DEFAULT']);
    });

    it('preserves INT UNSIGNED through unrelated change on MySQL', function () {
        if (!($this->adapter instanceof \Maho\Db\Adapter\Pdo\Mysql)) {
            $this->markTestSkipped('UNSIGNED is MySQL-only');
        }

        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true], 'ID')
            ->addColumn('count', Table::TYPE_INTEGER, null, [
                'unsigned' => true,
                'nullable' => false,
                'default' => 0,
            ], 'Count');
        $this->adapter->createTable($table);

        $before = $this->adapter->describeTable($this->testTableName)['count'];
        expect($before['UNSIGNED'])->toBeTrue();

        $this->adapter->modifyColumn($this->testTableName, 'count', ['comment' => 'Updated comment']);

        $after = $this->adapter->describeTable($this->testTableName)['count'];
        expect($after['UNSIGNED'])->toBeTrue();
        expect($after['NULLABLE'])->toBe($before['NULLABLE']);
        expect((string) $after['DEFAULT'])->toBe((string) $before['DEFAULT']);
    });

    it('drops legacy ON UPDATE CURRENT_TIMESTAMP and converts TIMESTAMP→DATETIME on surgical modify (MySQL)', function () {
        if (!($this->adapter instanceof \Maho\Db\Adapter\Pdo\Mysql)) {
            $this->markTestSkipped('TIMESTAMP/DATETIME and ON UPDATE are MySQL-specific concerns');
        }

        // Legacy column shape — TIMESTAMP with ON UPDATE CURRENT_TIMESTAMP. After the
        // maho-26.5.0 schema migration these don't exist in core anymore, but third-party
        // modules may still have them. The DBAL-based surgical path can't represent
        // ON UPDATE, so it gets dropped; the type also normalizes to DATETIME (which
        // Maho now uses everywhere via TYPE_TIMESTAMP). This test pins that behavior.
        $this->adapter->raw_query(sprintf(
            'CREATE TABLE %s (id INT NOT NULL PRIMARY KEY, '
            . "updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated At')",
            $this->adapter->quoteIdentifier($this->testTableName),
        ));

        $this->adapter->modifyColumn($this->testTableName, 'updated_at', ['comment' => 'New comment']);

        $row = $this->adapter->raw_fetchRow(sprintf(
            "SELECT DATA_TYPE, EXTRA, COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'updated_at'",
            $this->adapter->quote($this->testTableName),
        ));
        expect(strtolower((string) $row['DATA_TYPE']))->toBe('datetime');
        expect(stripos((string) $row['EXTRA'], 'on update CURRENT_TIMESTAMP'))->toBeFalse();
        expect((string) $row['COLUMN_COMMENT'])->toBe('New comment');
    });

    it('preserves auto_increment IDENTITY through unrelated change on MySQL', function () {
        if (!($this->adapter instanceof \Maho\Db\Adapter\Pdo\Mysql)) {
            $this->markTestSkipped('IDENTITY round-trip is MySQL-specific via this path');
        }

        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, [
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary' => true,
            ], 'ID')
            ->addColumn('name', Table::TYPE_TEXT, 64, ['nullable' => false], 'Name');
        $this->adapter->createTable($table);

        // Comment-only change to the AUTO_INCREMENT column should not strip auto_increment.
        $this->adapter->modifyColumn($this->testTableName, 'id', ['comment' => 'Primary key']);

        $describe = $this->adapter->describeTable($this->testTableName);
        expect($describe['id']['IDENTITY'])->toBeTrue();
    });

    it('rejects modification of generated columns on MySQL', function () {
        if (!($this->adapter instanceof \Maho\Db\Adapter\Pdo\Mysql)) {
            $this->markTestSkipped('Generated columns vary across engines');
        }

        $this->adapter->raw_query(sprintf(
            'CREATE TABLE %s (id INT NOT NULL PRIMARY KEY, a INT NOT NULL, '
            . "b INT GENERATED ALWAYS AS (a + 1) STORED COMMENT 'Computed')",
            $this->adapter->quoteIdentifier($this->testTableName),
        ));

        expect(fn() => $this->adapter->modifyColumn($this->testTableName, 'b', ['comment' => 'X']))
            ->toThrow(\Maho\Db\Exception::class, 'generated column');
    });

    it('preserves DEFAULT NULL on a NULLABLE TEXT column through unrelated change', function () {
        // Verifies MariaDB INFORMATION_SCHEMA quirk: explicit DEFAULT NULL must round-trip
        // as NULL, not as the string literal 'NULL'. Also exercises MySQL/PgSQL/SQLite
        // for cross-engine parity.
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true], 'ID')
            ->addColumn('note', Table::TYPE_TEXT, 64, [
                'nullable' => true,
                'default' => null,
            ], 'Note');
        $this->adapter->createTable($table);

        $before = $this->adapter->describeTable($this->testTableName)['note'];
        expect($before['DEFAULT'])->toBeNull();
        expect($before['NULLABLE'])->toBeTrue();

        // Touch only COMMENT — DEFAULT must remain NULL, not become the string 'NULL'.
        $this->adapter->modifyColumn($this->testTableName, 'note', ['comment' => 'Note text']);

        $after = $this->adapter->describeTable($this->testTableName)['note'];
        expect($after['DEFAULT'])->toBeNull();
        expect($after['NULLABLE'])->toBeTrue();
        expect($after['DATA_TYPE'])->toBe($before['DATA_TYPE']);
        expect($after['LENGTH'])->toBe($before['LENGTH']);
    });

    it('handles ENUM column comment-only modify on MySQL via DBAL', function () {
        if (!($this->adapter instanceof \Maho\Db\Adapter\Pdo\Mysql)) {
            $this->markTestSkipped('ENUM is MySQL-specific');
        }

        // DBAL has a dedicated EnumType in 4.x, so a surgical comment-only modify
        // preserves the ENUM definition rather than silently rewriting it to TEXT.
        $this->adapter->raw_query(sprintf(
            'CREATE TABLE %s (id INT NOT NULL PRIMARY KEY, '
            . "status ENUM('active', 'inactive') NOT NULL DEFAULT 'active')",
            $this->adapter->quoteIdentifier($this->testTableName),
        ));

        $this->adapter->modifyColumn($this->testTableName, 'status', ['comment' => 'Status']);

        $createSql = $this->adapter->raw_fetchRow(sprintf(
            'SHOW CREATE TABLE %s',
            $this->adapter->quoteIdentifier($this->testTableName),
        ), 'Create Table');
        expect(stripos((string) $createSql, "enum('active','inactive')"))->not->toBeFalse();
        expect(stripos((string) $createSql, "COMMENT 'Status'"))->not->toBeFalse();
    });

    it('migrates pre-existing TIMESTAMP columns to DATETIME on surgical modify (MySQL)', function () {
        if (!($this->adapter instanceof \Maho\Db\Adapter\Pdo\Mysql)) {
            $this->markTestSkipped('TIMESTAMP/DATETIME distinction is MySQL-specific');
        }

        // Verifies the new behavior post maho-26.5.0: any leftover physical TIMESTAMP
        // column (e.g. on a third-party module table not yet migrated) gets normalized
        // to DATETIME the first time it goes through the surgical path. This is the
        // intended consequence of TYPE_TIMESTAMP physically mapping to DATETIME.
        $this->adapter->raw_query(sprintf(
            'CREATE TABLE %s (id INT NOT NULL PRIMARY KEY, '
            . "created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created')",
            $this->adapter->quoteIdentifier($this->testTableName),
        ));

        $this->adapter->modifyColumn($this->testTableName, 'created_at', ['comment' => 'Created at']);

        $dataType = $this->adapter->raw_fetchRow(sprintf(
            "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'created_at'",
            $this->adapter->quote($this->testTableName),
        ), 'DATA_TYPE');
        expect(strtolower((string) $dataType))->toBe('datetime');
    });

    it('round-trips TIME column values across engines', function () {
        // TYPE_TIME → MySQL TIME, PgSQL TIME WITHOUT TIME ZONE, SQLite TEXT.
        // Insert a wall-clock value and assert it comes back byte-identical.
        // Introspection-direction round-trip works on MySQL/PgSQL (real TIME types)
        // but not on SQLite — type affinity flattens TIME to TEXT, with no way to
        // recover the original semantic type.
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true], 'ID')
            ->addColumn('opens_at', Table::TYPE_TIME, null, ['nullable' => false], 'Opens At');
        $this->adapter->createTable($table);

        $this->adapter->insert($this->testTableName, ['id' => 1, 'opens_at' => '09:30:00']);

        $row = $this->adapter->fetchRow(
            $this->adapter->select()->from($this->testTableName)->where('id = ?', 1),
        );
        expect($row['opens_at'])->toBe('09:30:00');

        if (!($this->adapter instanceof \Maho\Db\Adapter\Pdo\Sqlite)) {
            $describe = $this->adapter->describeTable($this->testTableName);
            expect($describe['opens_at']['DATA_TYPE'])->toBe(Table::TYPE_TIME);
        }
    });

    it('round-trips TINYINT column values across engines', function () {
        // TYPE_TINYINT → MySQL `tinyint` (1 byte), PgSQL `smallint` (no native — 2 bytes),
        // SQLite `INTEGER` (dynamic). Insert a value within the 1-byte range and assert
        // it round-trips. Introspection round-trip only works cleanly on MySQL — PgSQL
        // physically stores as smallint, SQLite has only INTEGER affinity.
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true], 'ID')
            ->addColumn('flags', Table::TYPE_TINYINT, null, ['nullable' => false, 'default' => 0], 'Flags');
        $this->adapter->createTable($table);

        $this->adapter->insert($this->testTableName, ['id' => 1, 'flags' => 42]);

        $row = $this->adapter->fetchRow(
            $this->adapter->select()->from($this->testTableName)->where('id = ?', 1),
        );
        expect((int) $row['flags'])->toBe(42);

        if ($this->adapter instanceof \Maho\Db\Adapter\Pdo\Mysql) {
            // Verify the physical type is actually tinyint (1 byte) — the whole point
            // of TYPE_TINYINT vs TYPE_SMALLINT.
            $dataType = $this->adapter->raw_fetchRow(sprintf(
                "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'flags'",
                $this->adapter->quote($this->testTableName),
            ), 'DATA_TYPE');
            expect(strtolower((string) $dataType))->toBe('tinyint');

            $describe = $this->adapter->describeTable($this->testTableName);
            expect($describe['flags']['DATA_TYPE'])->toBe(Table::TYPE_TINYINT);
        }
    });
});

describe('DDL Operations - Index Management', function () {
    it('adds index to existing table', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
            ->addColumn('email', Table::TYPE_TEXT, 255, [])
            ->addColumn('status', Table::TYPE_SMALLINT, null, []);

        $this->adapter->createTable($table);

        $indexName = $this->adapter->getIndexName($this->testTableName, ['email']);

        $this->adapter->addIndex($this->testTableName, $indexName, ['email']);

        $indexes = $this->adapter->getIndexList($this->testTableName);
        expect($indexes)->toBeArray();

        $foundIndex = false;
        foreach ($indexes as $index) {
            if (isset($index['COLUMNS_LIST']) && in_array('email', $index['COLUMNS_LIST'])) {
                $foundIndex = true;
                break;
            }
        }
        expect($foundIndex)->toBeTrue();
    });

    it('adds unique index', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
            ->addColumn('username', Table::TYPE_TEXT, 255, []);

        $this->adapter->createTable($table);

        $indexName = $this->adapter->getIndexName(
            $this->testTableName,
            ['username'],
            AdapterInterface::INDEX_TYPE_UNIQUE,
        );

        $this->adapter->addIndex(
            $this->testTableName,
            $indexName,
            ['username'],
            AdapterInterface::INDEX_TYPE_UNIQUE,
        );

        $indexes = $this->adapter->getIndexList($this->testTableName);

        $foundUniqueIndex = false;
        foreach ($indexes as $index) {
            if (isset($index['COLUMNS_LIST']) && in_array('username', $index['COLUMNS_LIST'])) {
                if ($index['INDEX_TYPE'] === AdapterInterface::INDEX_TYPE_UNIQUE) {
                    $foundUniqueIndex = true;
                    break;
                }
            }
        }
        expect($foundUniqueIndex)->toBeTrue();
    });

    it('adds composite index', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
            ->addColumn('first_name', Table::TYPE_TEXT, 255, [])
            ->addColumn('last_name', Table::TYPE_TEXT, 255, [])
            ->addColumn('status', Table::TYPE_SMALLINT, null, []);

        $this->adapter->createTable($table);

        $indexName = $this->adapter->getIndexName($this->testTableName, ['first_name', 'last_name', 'status']);

        $this->adapter->addIndex($this->testTableName, $indexName, ['first_name', 'last_name', 'status']);

        $indexes = $this->adapter->getIndexList($this->testTableName);

        $foundCompositeIndex = false;
        foreach ($indexes as $index) {
            if (
                isset($index['COLUMNS_LIST'])
                && in_array('first_name', $index['COLUMNS_LIST'])
                && in_array('last_name', $index['COLUMNS_LIST'])
                && in_array('status', $index['COLUMNS_LIST'])
            ) {
                $foundCompositeIndex = true;
                break;
            }
        }
        expect($foundCompositeIndex)->toBeTrue();
    });

    it('drops index from table', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
            ->addColumn('email', Table::TYPE_TEXT, 255, []);

        $indexName = $this->adapter->getIndexName($this->testTableName, ['email']);

        $table->addIndex($indexName, ['email']);

        $this->adapter->createTable($table);

        $indexesBefore = $this->adapter->getIndexList($this->testTableName);
        expect(count($indexesBefore))->toBeGreaterThan(1); // PRIMARY + email index

        $this->adapter->dropIndex($this->testTableName, $indexName);

        $indexesAfter = $this->adapter->getIndexList($this->testTableName);
        expect(count($indexesAfter))->toBeLessThan(count($indexesBefore));
    });
});

describe('DDL Operations - Foreign Key Management', function () {
    it('adds foreign key to existing table', function () {
        $parentTable = 'test_parent_' . uniqid();
        $table = $this->adapter->newTable($parentTable)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['identity' => true, 'nullable' => false, 'primary' => true])
            ->addColumn('name', Table::TYPE_TEXT, 255, []);

        $this->adapter->createTable($table);

        $childTable = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['identity' => true, 'nullable' => false, 'primary' => true])
            ->addColumn('parent_id', Table::TYPE_INTEGER, null, ['nullable' => false]);

        $this->adapter->createTable($childTable);

        // Add index first (required for FK)
        $this->adapter->addIndex(
            $this->testTableName,
            $this->adapter->getIndexName($this->testTableName, ['parent_id']),
            ['parent_id'],
        );

        $fkName = $this->adapter->getForeignKeyName($this->testTableName, 'parent_id', $parentTable, 'id');

        $this->adapter->addForeignKey(
            $fkName,
            $this->testTableName,
            'parent_id',
            $parentTable,
            'id',
            Table::ACTION_CASCADE,
        );

        $fks = $this->adapter->getForeignKeys($this->testTableName);
        expect($fks)->toBeArray();
        expect(count($fks))->toBeGreaterThan(0);

        // Cleanup
        $this->adapter->dropTable($this->testTableName);
        $this->adapter->dropTable($parentTable);
    });

    it('drops foreign key from table', function () {
        $parentTable = 'test_parent_' . uniqid();
        $table = $this->adapter->newTable($parentTable)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['identity' => true, 'nullable' => false, 'primary' => true]);

        $this->adapter->createTable($table);

        $fkName = $this->adapter->getForeignKeyName($this->testTableName, 'parent_id', $parentTable, 'id');

        $childTable = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['identity' => true, 'nullable' => false, 'primary' => true])
            ->addColumn('parent_id', Table::TYPE_INTEGER, null, [])
            ->addIndex(
                $this->adapter->getIndexName($this->testTableName, ['parent_id']),
                ['parent_id'],
            )
            ->addForeignKey(
                $fkName,
                'parent_id',
                $parentTable,
                'id',
                Table::ACTION_CASCADE,
            );

        $this->adapter->createTable($childTable);

        $fksBefore = $this->adapter->getForeignKeys($this->testTableName);
        expect(count($fksBefore))->toBeGreaterThan(0);

        $this->adapter->dropForeignKey($this->testTableName, $fkName);

        $fksAfter = $this->adapter->getForeignKeys($this->testTableName);
        expect(count($fksAfter))->toBeLessThan(count($fksBefore));

        // Cleanup
        $this->adapter->dropTable($this->testTableName);
        $this->adapter->dropTable($parentTable);
    });

    it('handles foreign key with different actions', function () {
        $parentTable = 'test_parent_' . uniqid();
        $table = $this->adapter->newTable($parentTable)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['identity' => true, 'nullable' => false, 'primary' => true]);

        $this->adapter->createTable($table);

        // Test CASCADE delete
        $childTable1 = $this->testTableName . '_cascade';
        $table = $this->adapter->newTable($childTable1)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['identity' => true, 'nullable' => false, 'primary' => true])
            ->addColumn('parent_id', Table::TYPE_INTEGER, null, [])
            ->addIndex(
                $this->adapter->getIndexName($childTable1, ['parent_id']),
                ['parent_id'],
            )
            ->addForeignKey(
                $this->adapter->getForeignKeyName($childTable1, 'parent_id', $parentTable, 'id'),
                'parent_id',
                $parentTable,
                'id',
                Table::ACTION_CASCADE,
            );

        $this->adapter->createTable($table);
        expect($this->adapter->isTableExists($childTable1))->toBeTrue();

        // Test SET NULL
        $childTable2 = $this->testTableName . '_setnull';
        $table = $this->adapter->newTable($childTable2)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['identity' => true, 'nullable' => false, 'primary' => true])
            ->addColumn('parent_id', Table::TYPE_INTEGER, null, ['nullable' => true])
            ->addIndex(
                $this->adapter->getIndexName($childTable2, ['parent_id']),
                ['parent_id'],
            )
            ->addForeignKey(
                $this->adapter->getForeignKeyName($childTable2, 'parent_id', $parentTable, 'id'),
                'parent_id',
                $parentTable,
                'id',
                Table::ACTION_SET_NULL,
            );

        $this->adapter->createTable($table);
        expect($this->adapter->isTableExists($childTable2))->toBeTrue();

        // Cleanup
        $this->adapter->dropTable($childTable1);
        $this->adapter->dropTable($childTable2);
        $this->adapter->dropTable($parentTable);
    });
});

describe('DDL Operations - Table Management', function () {
    it('drops table successfully', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true]);

        $this->adapter->createTable($table);
        expect($this->adapter->isTableExists($this->testTableName))->toBeTrue();

        $this->adapter->dropTable($this->testTableName);
        expect($this->adapter->isTableExists($this->testTableName))->toBeFalse();
    });

    it('truncates table successfully', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['identity' => true, 'nullable' => false, 'primary' => true])
            ->addColumn('name', Table::TYPE_TEXT, 255, []);

        $this->adapter->createTable($table);

        // Insert test data
        $this->adapter->insert($this->testTableName, ['name' => 'Test 1']);
        $this->adapter->insert($this->testTableName, ['name' => 'Test 2']);
        $this->adapter->insert($this->testTableName, ['name' => 'Test 3']);

        $countBefore = $this->adapter->fetchOne("SELECT COUNT(*) FROM {$this->testTableName}");
        expect((int) $countBefore)->toBe(3);

        $this->adapter->truncateTable($this->testTableName);

        $countAfter = $this->adapter->fetchOne("SELECT COUNT(*) FROM {$this->testTableName}");
        expect((int) $countAfter)->toBe(0);

        // Table should still exist
        expect($this->adapter->isTableExists($this->testTableName))->toBeTrue();
    });

    it('renames table successfully', function () {
        $oldName = $this->testTableName;
        $newName = $this->testTableName . '_renamed';

        $table = $this->adapter->newTable($oldName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
            ->addColumn('name', Table::TYPE_TEXT, 255, []);

        $this->adapter->createTable($table);

        expect($this->adapter->isTableExists($oldName))->toBeTrue();
        expect($this->adapter->isTableExists($newName))->toBeFalse();

        $this->adapter->renameTable($oldName, $newName);

        expect($this->adapter->isTableExists($oldName))->toBeFalse();
        expect($this->adapter->isTableExists($newName))->toBeTrue();

        // Cleanup
        $this->adapter->dropTable($newName);
    });

    it('creates temporary table', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
            ->addColumn('data', Table::TYPE_TEXT, 255, [])
            ->setOption('type', 'TEMPORARY');

        $this->adapter->createTable($table);

        // Temporary tables exist for the current connection
        expect($this->adapter->isTableExists($this->testTableName))->toBeTrue();

        // Can insert data
        $this->adapter->insert($this->testTableName, ['id' => 1, 'data' => 'test']);

        $result = $this->adapter->fetchOne("SELECT data FROM {$this->testTableName} WHERE id = 1");
        expect($result)->toBe('test');
    });
});

describe('DDL Operations - Table Options', function () {
    it('creates table with specific engine', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
            ->addColumn('data', Table::TYPE_TEXT, 255, [])
            ->setOption('type', 'InnoDB');

        $this->adapter->createTable($table);

        expect($this->adapter->isTableExists($this->testTableName))->toBeTrue();
    });

    it('creates table with specific charset and collation', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
            ->addColumn('text', Table::TYPE_TEXT, 255, [])
            ->setOption('charset', 'utf8mb4')
            ->setOption('collate', 'utf8mb4_unicode_ci');

        $this->adapter->createTable($table);

        expect($this->adapter->isTableExists($this->testTableName))->toBeTrue();
    });

    it('creates table with comment', function () {
        $table = $this->adapter->newTable($this->testTableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
            ->setComment('This is a test table for DDL operations');

        $this->adapter->createTable($table);

        expect($this->adapter->isTableExists($this->testTableName))->toBeTrue();
    });
});
