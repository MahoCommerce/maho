<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
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

function applierTable(string $name): TableEditor
{
    return Table::editor()->setUnquotedName($name);
}

// --- autoIncrementRestores ----------------------------------------------

it('re-asserts AUTO_INCREMENT after a primary-key rebuild on MySQL', function () {
    $live = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->setAutoincrement(true)
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('sku')
                ->setTypeName(Types::STRING)
                ->setLength(64)
                ->create(),
        )
        ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id', 'sku')->create())
        ->create();

    $target = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->setAutoincrement(true)
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('sku')
                ->setTypeName(Types::STRING)
                ->setLength(64)
                ->create(),
        )
        ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
        ->create();

    $result = invokeApplierMethod('autoIncrementRestores', [new MySQLPlatform(), [$live], [$target]]);

    expect($result)->toHaveCount(1);
    expect($result[0])->toContain('MODIFY');
    expect($result[0])->toContain('AUTO_INCREMENT');
    expect($result[0])->toContain('id');
});

it('emits no restore when the primary key is unchanged', function () {
    $pk = fn(): PrimaryKeyConstraint => PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create();

    $live = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->setAutoincrement(true)
                ->create(),
        )
        ->addPrimaryKeyConstraint($pk())
        ->create();

    $target = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->setAutoincrement(true)
                ->create(),
        )
        ->addPrimaryKeyConstraint($pk())
        ->create();

    $result = invokeApplierMethod('autoIncrementRestores', [new MySQLPlatform(), [$live], [$target]]);

    expect($result)->toBe([]);
});

it('emits no restore for a table without an autoincrement column', function () {
    $live = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('code')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('val')
                ->setTypeName(Types::INTEGER)
                ->create(),
        )
        ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('code', 'val')->create())
        ->create();

    $target = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('code')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('val')
                ->setTypeName(Types::INTEGER)
                ->create(),
        )
        ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('code')->create())
        ->create();

    $result = invokeApplierMethod('autoIncrementRestores', [new MySQLPlatform(), [$live], [$target]]);

    expect($result)->toBe([]);
});

it('never emits an AUTO_INCREMENT restore on a non-MySQL platform', function () {
    $live = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->setAutoincrement(true)
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('sku')
                ->setTypeName(Types::STRING)
                ->setLength(64)
                ->create(),
        )
        ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id', 'sku')->create())
        ->create();

    $target = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->setAutoincrement(true)
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('sku')
                ->setTypeName(Types::STRING)
                ->setLength(64)
                ->create(),
        )
        ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
        ->create();

    $result = invokeApplierMethod('autoIncrementRestores', [new PostgreSQLPlatform(), [$live], [$target]]);

    expect($result)->toBe([]);
});

// --- sqliteRebuildTable -------------------------------------------------

it('rebuilds a SQLite table preserving the shared columns data', function () {
    $live = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->setAutoincrement(true)
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('name')
                ->setTypeName(Types::STRING)
                ->setLength(64)
                ->create(),
        )
        ->create();

    $target = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->setAutoincrement(true)
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('name')
                ->setTypeName(Types::STRING)
                ->setLength(64)
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('extra')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->setNotNull(false)
                ->create(),
        )
        ->create();

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
    $live = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->create(),
        )
        ->create();

    $target = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('mandatory')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->setNotNull(true)
                ->create(),
        )
        ->create();

    expect(fn() => invokeApplierMethod('sqliteRebuildTable', [new SQLitePlatform(), $live, $target]))
        ->toThrow(UnsupportedMigrationException::class);
});

it('allows a new NOT NULL column with a default during a SQLite rebuild', function () {
    $live = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->create(),
        )
        ->create();

    $target = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('status')
                ->setTypeName(Types::STRING)
                ->setLength(16)
                ->setNotNull(true)
                ->setDefaultValue('pending')
                ->create(),
        )
        ->create();

    $result = invokeApplierMethod('sqliteRebuildTable', [new SQLitePlatform(), $live, $target]);

    expect($result)->not->toBeEmpty();
    expect(implode("\n", $result))->toContain('CREATE TABLE t ');
});

it('refuses a SQLite rebuild when no columns are shared', function () {
    $live = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('old_col')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->setNotNull(false)
                ->create(),
        )
        ->create();

    $target = applierTable('t')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('new_col')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->setNotNull(false)
                ->create(),
        )
        ->create();

    expect(fn() => invokeApplierMethod('sqliteRebuildTable', [new SQLitePlatform(), $live, $target]))
        ->toThrow(UnsupportedMigrationException::class);
});

// --- engine conversions -------------------------------------------------

it('emits one quoted ALTER ... ENGINE=InnoDB per legacy table', function () {
    $result = invokeApplierMethod('engineConversionStatements', [new MySQLPlatform(), ['oauth_nonce', 'log_url']]);

    expect($result)->toBe([
        'ALTER TABLE `oauth_nonce` ENGINE=InnoDB',
        'ALTER TABLE `log_url` ENGINE=InnoDB',
    ]);
});

it('emits no engine conversions on an empty table list', function () {
    expect(invokeApplierMethod('engineConversionStatements', [new MySQLPlatform(), []]))->toBe([]);
});

it('skips the engine pass entirely on PostgreSQL and SQLite', function (string $platformClass) {
    // A real connection, so a missed guard would surface as a query error
    // against a database that has no information_schema.TABLES at all.
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

    expect(invokeApplierMethod('engineConversions', [$connection, new $platformClass(), '']))->toBe([]);
})->with([
    'postgres' => PostgreSQLPlatform::class,
    'sqlite' => SQLitePlatform::class,
]);

// --- charset conversions ------------------------------------------------

it('converts an undeclared utf8mb3 table with CONVERT TO', function () {
    $result = invokeApplierMethod('charsetConversionStatements', [
        new MySQLPlatform(),
        ['thirdparty_log' => 'utf8mb3_general_ci', 'thirdparty_key' => 'utf8_bin'],
        [],
    ]);

    expect($result)->toBe([[], [
        'ALTER TABLE `thirdparty_log` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
        'ALTER TABLE `thirdparty_key` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
    ]]);
});

it('converts each utf8mb3 column of a declared table and keeps its type', function () {
    // CONVERT TO would widen TEXT to MEDIUMTEXT, and the declared TEXT would then
    // make the diff plan a change back on every run.
    $live = applierTable('cms_block')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('block_id')
                ->setTypeName(Types::INTEGER)
                ->setUnsigned(true)
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('title')
                ->setTypeName(Types::STRING)
                ->setLength(255)
                ->setDefaultValue("it's")
                ->setComment('Block title')
                ->setCharset('utf8mb3')
                ->setCollation('utf8mb3_general_ci')
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('content')
                ->setTypeName(Types::TEXT)
                ->setLength(65535)
                ->setNotNull(false)
                ->setCharset('utf8mb3')
                ->setCollation('utf8mb3_bin')
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('identifier')
                ->setTypeName(Types::STRING)
                ->setLength(255)
                ->setCharset('utf8mb4')
                ->setCollation('utf8mb4_general_ci')
                ->create(),
        )
        ->create();

    $result = invokeApplierMethod('charsetConversionStatements', [
        new MySQLPlatform(),
        ['cms_block' => 'utf8mb3_general_ci'],
        ['cms_block' => $live],
    ]);

    expect($result)->toBe([[
        'ALTER TABLE `cms_block` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,'
        . " MODIFY `title` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'it''s' COMMENT 'Block title',"
        . ' MODIFY `content` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL',
    ], []]);
});

it('skips the charset pass entirely on PostgreSQL and SQLite', function (string $platformClass) {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

    expect(invokeApplierMethod('charsetConversions', [$connection, new $platformClass(), '', []]))->toBe([[], []]);
})->with([
    'postgres' => PostgreSQLPlatform::class,
    'sqlite' => SQLitePlatform::class,
]);
