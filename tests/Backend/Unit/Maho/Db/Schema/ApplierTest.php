<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema\Applier;
use Maho\Db\Schema\UnsupportedMigrationException;

/**
 * Unit coverage for the pure, connection-free Applier paths the CI Docker
 * matrix otherwise exercises end-to-end: the MySQL AUTO_INCREMENT re-assertion
 * after a primary-key rebuild, and the SQLite table-rebuild SQL (including the
 * un-backfillable NOT NULL refusal). No Maho bootstrap or database.
 */

/** @param list<mixed> $args */
function invokeApplierMethod(string $method, array $args): mixed
{
    $ref = new ReflectionMethod(Applier::class, $method);
    return $ref->invoke(null, ...$args);
}

function applierTable(string $name): Table
{
    return (new Schema())->createTable($name);
}

// --- autoIncrementRestores ----------------------------------------------

it('re-asserts AUTO_INCREMENT after a primary-key rebuild on MySQL', function () {
    $live = applierTable('t');
    $live->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $live->addColumn('sku', Types::STRING, ['length' => 64]);
    $live->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id', 'sku')->create(),
    );

    $target = applierTable('t');
    $target->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $target->addColumn('sku', Types::STRING, ['length' => 64]);
    $target->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
    );

    $result = invokeApplierMethod('autoIncrementRestores', [new MySQLPlatform(), [$live], [$target]]);

    expect($result)->toHaveCount(1);
    expect($result[0])->toContain('MODIFY');
    expect($result[0])->toContain('AUTO_INCREMENT');
    expect($result[0])->toContain('id');
});

it('emits no restore when the primary key is unchanged', function () {
    $pk = fn(): PrimaryKeyConstraint => PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create();

    $live = applierTable('t');
    $live->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $live->addPrimaryKeyConstraint($pk());

    $target = applierTable('t');
    $target->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $target->addPrimaryKeyConstraint($pk());

    $result = invokeApplierMethod('autoIncrementRestores', [new MySQLPlatform(), [$live], [$target]]);

    expect($result)->toBe([]);
});

it('emits no restore for a table without an autoincrement column', function () {
    $live = applierTable('t');
    $live->addColumn('code', Types::STRING, ['length' => 32]);
    $live->addColumn('val', Types::INTEGER, []);
    $live->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('code', 'val')->create(),
    );

    $target = applierTable('t');
    $target->addColumn('code', Types::STRING, ['length' => 32]);
    $target->addColumn('val', Types::INTEGER, []);
    $target->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('code')->create(),
    );

    $result = invokeApplierMethod('autoIncrementRestores', [new MySQLPlatform(), [$live], [$target]]);

    expect($result)->toBe([]);
});

it('never emits an AUTO_INCREMENT restore on a non-MySQL platform', function () {
    $live = applierTable('t');
    $live->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $live->addColumn('sku', Types::STRING, ['length' => 64]);
    $live->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id', 'sku')->create(),
    );

    $target = applierTable('t');
    $target->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $target->addColumn('sku', Types::STRING, ['length' => 64]);
    $target->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
    );

    $result = invokeApplierMethod('autoIncrementRestores', [new PostgreSQLPlatform(), [$live], [$target]]);

    expect($result)->toBe([]);
});

// --- sqliteRebuildTable -------------------------------------------------

it('rebuilds a SQLite table preserving the shared columns data', function () {
    $live = applierTable('t');
    $live->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $live->addColumn('name', Types::STRING, ['length' => 64]);

    $target = applierTable('t');
    $target->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $target->addColumn('name', Types::STRING, ['length' => 64]);
    $target->addColumn('extra', Types::STRING, ['length' => 32, 'notnull' => false]);

    $result = invokeApplierMethod('sqliteRebuildTable', [new SQLitePlatform(), $live, $target]);

    // Snapshot shared cols → drop original → recreate from target → copy back → drop temp.
    expect($result[0])->toContain('CREATE TEMPORARY TABLE');
    expect($result[0])->toContain('__maho_tmp_t');
    expect($result[1])->toBe('DROP TABLE "t"');
    $joined = implode("\n", $result);
    expect($joined)->toContain('CREATE TABLE t ');
    expect($joined)->toContain('INSERT INTO "t"');
    expect($result[count($result) - 1])->toContain('DROP TABLE "__maho_tmp_t"');

    // Only the shared columns (id, name) are copied; the new "extra" column is not.
    foreach ($result as $stmt) {
        if (str_starts_with($stmt, 'INSERT INTO')) {
            expect($stmt)->not->toContain('extra');
        }
    }
});

it('refuses to add a NOT NULL column with no default during a SQLite rebuild', function () {
    $live = applierTable('t');
    $live->addColumn('id', Types::INTEGER, ['unsigned' => true]);

    $target = applierTable('t');
    $target->addColumn('id', Types::INTEGER, ['unsigned' => true]);
    $target->addColumn('mandatory', Types::STRING, ['length' => 32, 'notnull' => true]);

    expect(fn() => invokeApplierMethod('sqliteRebuildTable', [new SQLitePlatform(), $live, $target]))
        ->toThrow(UnsupportedMigrationException::class);
});

it('allows a new NOT NULL column with a default during a SQLite rebuild', function () {
    $live = applierTable('t');
    $live->addColumn('id', Types::INTEGER, ['unsigned' => true]);

    $target = applierTable('t');
    $target->addColumn('id', Types::INTEGER, ['unsigned' => true]);
    $target->addColumn('status', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'pending']);

    $result = invokeApplierMethod('sqliteRebuildTable', [new SQLitePlatform(), $live, $target]);

    expect($result)->not->toBeEmpty();
    expect(implode("\n", $result))->toContain('CREATE TABLE t ');
});

it('refuses a SQLite rebuild when no columns are shared', function () {
    $live = applierTable('t');
    $live->addColumn('old_col', Types::STRING, ['length' => 32, 'notnull' => false]);

    $target = applierTable('t');
    $target->addColumn('new_col', Types::STRING, ['length' => 32, 'notnull' => false]);

    expect(fn() => invokeApplierMethod('sqliteRebuildTable', [new SQLitePlatform(), $live, $target]))
        ->toThrow(UnsupportedMigrationException::class);
});
