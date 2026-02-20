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
            ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, [
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
