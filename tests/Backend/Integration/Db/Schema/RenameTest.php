<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema\Applier;
use Maho\Db\Schema\Renamer;
use Maho\Db\Schema\UnsupportedMigrationException;

uses(Tests\MahoBackendTestCase::class);

/**
 * End-to-end coverage against the live database: the unit tests fix the SQL
 * shape, this fixes what it does to real rows. Both the before and the after
 * state are built through Applier, so "second plan is empty" measures
 * convergence rather than canonicalization noise.
 */

const RENAME_OLD_TABLE = 'maho_rename_probe_old';
const RENAME_NEW_TABLE = 'maho_rename_probe_new';
const RENAME_CHILD_TABLE = 'maho_rename_probe_child';

function renameProbeTable(
    string $table,
    string $emailColumn,
    bool $withHistory,
    string $index = 'IDX_MAHO_PROBE_EMAIL',
): Table {
    $options = ['engine' => 'InnoDB', 'charset' => 'utf8', 'collation' => 'utf8_general_ci'];
    if ($withHistory) {
        $options += Renamer::renamed(from: RENAME_OLD_TABLE, columns: ['customer_email' => 'legacy_email']);
    }

    return Table::editor()
        ->setUnquotedName($table)
        ->addColumn(
            Column::editor()
                ->setUnquotedName('entity_id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->setAutoincrement(true)
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName($emailColumn)
                ->setTypeName(Types::STRING)
                ->setLength(255)
                ->setNotNull(false)
                ->create(),
        )
        ->addIndex(Index::editor()->setUnquotedName($index)->setUnquotedColumnNames($emailColumn)->create())
        ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
        ->setOptions($options)
        ->create();
}

function renameProbeSchema(
    string $table,
    string $emailColumn,
    bool $withHistory,
    string $index = 'IDX_MAHO_PROBE_EMAIL',
): Schema {
    return Schema::editor()->addTable(renameProbeTable($table, $emailColumn, $withHistory, $index))->create();
}

function renameProbeSchemaWithChild(string $table, string $emailColumn, bool $withHistory): Schema
{
    $child = Table::editor()
        ->setUnquotedName(RENAME_CHILD_TABLE)
        ->addColumn(
            Column::editor()
                ->setUnquotedName('entity_id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->setAutoincrement(true)
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('parent_id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->create(),
        )
        ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
        ->addForeignKeyConstraint(
            ForeignKeyConstraint::editor()
                ->setUnquotedName('FK_MAHO_PROBE_CHILD')
                ->setUnquotedReferencingColumnNames('parent_id')
                ->setUnquotedReferencedTableName($table)
                ->setUnquotedReferencedColumnNames('entity_id')
                ->create(),
        )
        ->setOptions(['engine' => 'InnoDB', 'charset' => 'utf8', 'collation' => 'utf8_general_ci'])
        ->create();

    return Schema::editor()
        ->addTable(renameProbeTable($table, $emailColumn, $withHistory))
        ->addTable($child)
        ->create();
}

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_setup');
    $this->connection = $this->adapter->getConnection();

    $this->converge = function (Schema $target): array {
        $sql = Applier::plan($this->connection, $target, '', false);
        if ($sql !== []) {
            Applier::execute($this->adapter, $sql);
        }
        return $sql;
    };

    // Child first: it holds the foreign key onto the probe tables.
    foreach ([RENAME_CHILD_TABLE, RENAME_OLD_TABLE, RENAME_NEW_TABLE] as $table) {
        $this->adapter->dropTable($table);
    }

    ($this->converge)(renameProbeSchema(RENAME_OLD_TABLE, 'legacy_email', false));
    $this->adapter->insert(RENAME_OLD_TABLE, ['legacy_email' => 'shopper@example.com']);
});

afterEach(function () {
    foreach ([RENAME_CHILD_TABLE, RENAME_OLD_TABLE, RENAME_NEW_TABLE] as $table) {
        $this->adapter->dropTable($table);
    }
});

it('renames the table and the column, and keeps the row', function () {
    $target = renameProbeSchema(RENAME_NEW_TABLE, 'customer_email', true);

    $sql = ($this->converge)($target);

    expect($sql)->not->toBe([]);
    expect($this->adapter->isTableExists(RENAME_OLD_TABLE))->toBeFalse();
    expect($this->adapter->isTableExists(RENAME_NEW_TABLE))->toBeTrue();

    $row = $this->adapter->fetchRow($this->adapter->select()->from(RENAME_NEW_TABLE));
    expect($row['customer_email'])->toBe('shopper@example.com');
    expect((int) $row['entity_id'])->toBe(1);
});

it('converges, so a second run has nothing left to do', function () {
    $target = renameProbeSchema(RENAME_NEW_TABLE, 'customer_email', true);
    ($this->converge)($target);

    // Rebuilt from scratch: the pre-pass must read the live database, not a
    // Schema a previous plan mutated.
    $second = Applier::plan($this->connection, renameProbeSchema(RENAME_NEW_TABLE, 'customer_email', true), '', false);

    expect($second)->toBe([]);
});

it('leaves a database that never carried the old names alone', function () {
    $this->adapter->dropTable(RENAME_OLD_TABLE);

    $sql = ($this->converge)(renameProbeSchema(RENAME_NEW_TABLE, 'customer_email', true));

    expect(preg_grep('/RENAME\s+(TO|COLUMN)/i', $sql))->toBe([]);
    expect($this->adapter->isTableExists(RENAME_NEW_TABLE))->toBeTrue();
});

it('keeps a foreign key onto a renamed table usable', function () {
    ($this->converge)(renameProbeSchemaWithChild(RENAME_OLD_TABLE, 'legacy_email', false));

    ($this->converge)(renameProbeSchemaWithChild(RENAME_NEW_TABLE, 'customer_email', true));

    $parentId = (int) $this->adapter->fetchOne(
        $this->adapter->select()->from(RENAME_NEW_TABLE, ['entity_id']),
    );
    $this->adapter->insert(RENAME_CHILD_TABLE, ['parent_id' => $parentId]);

    $row = $this->adapter->fetchRow($this->adapter->select()->from(RENAME_CHILD_TABLE));
    expect((int) $row['parent_id'])->toBe($parentId);
});

it('refuses to rename when both tables exist', function () {
    // Its own index name: SQLite and Postgres scope index names to the database,
    // not to the table, and this is the one test where both probes coexist.
    ($this->converge)(renameProbeSchema(RENAME_NEW_TABLE, 'customer_email', false, 'IDX_MAHO_PROBE_EMAIL_2'));

    expect(fn() => Applier::plan(
        $this->connection,
        renameProbeSchema(RENAME_NEW_TABLE, 'customer_email', true),
        '',
        false,
    ))->toThrow(UnsupportedMigrationException::class, 'both tables exist');
});
