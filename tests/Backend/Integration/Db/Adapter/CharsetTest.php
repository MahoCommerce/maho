<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Adapter\Pdo\Mysql;
use Maho\Db\Schema\Applier;

uses(Tests\MahoBackendTestCase::class);

const CHARSET_PARENT_TABLE = 'test_charset_parent';
const CHARSET_CHILD_TABLE = 'test_charset_child';

/**
 * A parent and a child joined by a VARCHAR foreign key: MySQL refuses the key
 * when the two columns are in different charsets.
 */
function charsetProbeSchema(string $charset): Schema
{
    $schema = new Schema();

    $parent = $schema->createTable(CHARSET_PARENT_TABLE);
    $parent->addColumn('code', Types::STRING, ['length' => 32]);
    $parent->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('code')->create());

    $child = $schema->createTable(CHARSET_CHILD_TABLE);
    $child->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $child->addColumn('parent_code', Types::STRING, ['length' => 32]);
    $child->addColumn('note', Types::TEXT, ['length' => 65535, 'notnull' => false, 'comment' => 'Free text']);
    $child->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create());
    $child->addIndex(['parent_code'], 'IDX_TEST_CHARSET_CHILD_PARENT_CODE');
    $child->addForeignKeyConstraint(CHARSET_PARENT_TABLE, ['parent_code'], ['code'], [], 'FK_TEST_CHARSET_CHILD_PARENT');

    foreach ([$parent, $child] as $table) {
        $table->addOption('engine', 'InnoDB');
        $table->addOption('charset', $charset);
        $table->addOption('collation', $charset . '_general_ci');
    }

    return $schema;
}

/** @return array<string, array{charset: ?string, type: string}> column name => charset and type */
function charsetProbeColumns(Mysql $adapter, string $table): array
{
    $columns = [];
    $rows = $adapter->fetchAll(
        'SELECT COLUMN_NAME, CHARACTER_SET_NAME, DATA_TYPE FROM information_schema.COLUMNS'
        . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
        [$table],
    );
    foreach ($rows as $row) {
        $columns[$row['COLUMN_NAME']] = ['charset' => $row['CHARACTER_SET_NAME'], 'type' => $row['DATA_TYPE']];
    }

    return $columns;
}

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_setup');
    if (!($this->adapter instanceof Mysql)) {
        $this->markTestSkipped('MySQL-only test: PostgreSQL and SQLite already store 4-byte characters.');
    }

    $this->adapter->dropTable(CHARSET_CHILD_TABLE);
    $this->adapter->dropTable(CHARSET_PARENT_TABLE);
});

afterEach(function () {
    if ($this->adapter instanceof Mysql) {
        $this->adapter->dropTable(CHARSET_CHILD_TABLE);
        $this->adapter->dropTable(CHARSET_PARENT_TABLE);
    }
});

it('connects with utf8mb4 and the table collation', function () {
    expect($this->adapter->fetchOne('SELECT @@character_set_client'))->toBe('utf8mb4');
    expect($this->adapter->fetchOne('SELECT @@collation_connection'))->toBe('utf8mb4_general_ci');
});

it('keeps utf8mb4 when local.xml still holds SET NAMES utf8', function () {
    $config = Mage::getConfig()->getResourceConnectionConfig('core_setup')->asArray();
    $config['initStatements'] = 'SET NAMES utf8';

    $method = new ReflectionMethod(Mage_Core_Model_Resource::class, '_newConnection');
    $connection = $method->invoke(Mage::getSingleton('core/resource'), 'pdo_mysql', $config);

    expect($connection->fetchOne('SELECT @@character_set_client'))->toBe('utf8mb4');
    $connection->closeConnection();
});

it('has no table left in utf8mb3', function () {
    expect(Applier::legacyCharsetTables($this->adapter->getConnection()))->toBe([]);
});

it('converts a declared utf8mb3 table, keeps each column type, and converges', function () {
    Applier::execute($this->adapter, Applier::plan($this->adapter->getConnection(), charsetProbeSchema('utf8mb3'), '', false));
    // A column that a third-party module added, which no schema.php declares.
    $this->adapter->query(sprintf('ALTER TABLE %s ADD extra VARCHAR(64) NULL', CHARSET_CHILD_TABLE));

    $connection = $this->adapter->getConnection();
    $target = charsetProbeSchema('utf8mb4');

    // The pending conversion must not report the declared schema as behind,
    // or every store would show the update page until it runs.
    expect(Applier::plan($connection, $target, '', false))->toBe([]);

    $sql = Applier::plan($connection, $target);
    expect($sql)->toHaveCount(2);
    Applier::execute($this->adapter, $sql);

    $legacy = Applier::legacyCharsetTables($connection);
    expect($legacy)->not->toHaveKey(CHARSET_PARENT_TABLE);
    expect($legacy)->not->toHaveKey(CHARSET_CHILD_TABLE);
    expect(charsetProbeColumns($this->adapter, CHARSET_CHILD_TABLE))->toBe([
        'entity_id' => ['charset' => null, 'type' => 'int'],
        'parent_code' => ['charset' => 'utf8mb4', 'type' => 'varchar'],
        'note' => ['charset' => 'utf8mb4', 'type' => 'text'],
        'extra' => ['charset' => 'utf8mb4', 'type' => 'varchar'],
    ]);
    expect($this->adapter->getForeignKeys(CHARSET_CHILD_TABLE))->toHaveKey('FK_TEST_CHARSET_CHILD_PARENT');
    expect($this->adapter->fetchOne(
        'SELECT COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [CHARSET_CHILD_TABLE, 'note'],
    ))->toBe('Free text');

    $this->adapter->insert(CHARSET_PARENT_TABLE, ['code' => 'a']);
    $this->adapter->insert(CHARSET_CHILD_TABLE, ['parent_code' => 'a', 'note' => "\u{1F60A}", 'extra' => "\u{1F389}"]);
    expect($this->adapter->fetchOne(sprintf('SELECT note FROM %s', CHARSET_CHILD_TABLE)))->toBe("\u{1F60A}");

    expect(Applier::plan($connection, charsetProbeSchema('utf8mb4')))->toBe([]);
});

it('converts an undeclared utf8mb3 table', function () {
    $table = 'test_charset_undeclared_' . uniqid();
    $this->adapter->query(sprintf(
        'CREATE TABLE %s (id INT NOT NULL, note TEXT) ENGINE=InnoDB CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci',
        $this->adapter->quoteIdentifier($table),
    ));

    try {
        expect(Applier::legacyCharsetTables($this->adapter->getConnection()))->toHaveKey($table);

        Applier::execute($this->adapter, Applier::plan($this->adapter->getConnection(), new Schema()));

        expect(Applier::legacyCharsetTables($this->adapter->getConnection()))->not->toHaveKey($table);
    } finally {
        $this->adapter->dropTable($table);
    }
});

it('scopes the conversion to the prefix when the database is shared', function () {
    $table = 'otherapp_charset_' . uniqid();
    $this->adapter->query(sprintf(
        'CREATE TABLE %s (id INT NOT NULL) ENGINE=InnoDB CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci',
        $this->adapter->quoteIdentifier($table),
    ));

    try {
        expect(Applier::legacyCharsetTables($this->adapter->getConnection(), 'maho_'))->not->toHaveKey($table);
    } finally {
        $this->adapter->dropTable($table);
    }
});
