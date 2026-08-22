<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
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

function renameProbeSchema(string $table, string $emailColumn, bool $withHistory): Schema
{
    $schema = new Schema();
    $probe = $schema->createTable($table);
    $probe->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $probe->addColumn($emailColumn, Types::STRING, ['length' => 255, 'notnull' => false]);
    $probe->addIndex([$emailColumn], 'IDX_MAHO_PROBE_EMAIL');
    $probe->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $probe->addOption('engine', 'InnoDB');
    $probe->addOption('charset', 'utf8');
    $probe->addOption('collation', 'utf8_general_ci');

    if ($withHistory) {
        Renamer::renamed($probe, from: RENAME_OLD_TABLE, columns: ['customer_email' => 'legacy_email']);
    }

    return $schema;
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

    foreach ([RENAME_OLD_TABLE, RENAME_NEW_TABLE] as $table) {
        $this->adapter->dropTable($table);
    }

    ($this->converge)(renameProbeSchema(RENAME_OLD_TABLE, 'legacy_email', false));
    $this->adapter->insert(RENAME_OLD_TABLE, ['legacy_email' => 'shopper@example.com']);
});

afterEach(function () {
    foreach ([RENAME_OLD_TABLE, RENAME_NEW_TABLE] as $table) {
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

it('refuses to rename when both tables exist', function () {
    ($this->converge)(renameProbeSchema(RENAME_NEW_TABLE, 'customer_email', false));

    expect(fn() => Applier::plan(
        $this->connection,
        renameProbeSchema(RENAME_NEW_TABLE, 'customer_email', true),
        '',
        false,
    ))->toThrow(UnsupportedMigrationException::class, 'both tables exist');
});
