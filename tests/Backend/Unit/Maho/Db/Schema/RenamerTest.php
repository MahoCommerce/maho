<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema\Renamer;
use Maho\Db\Schema\UnsupportedMigrationException;

/**
 * Unit coverage for the rename pre-pass: the declaration API, the rules that
 * refuse an ambiguous history, the four apply rules, and the SQL each platform
 * gets. No bootstrap.
 */

function renamerTable(Schema $schema, string $name): Table
{
    $table = $schema->createTable($name);
    $table->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $table->addColumn('customer_email', Types::STRING, ['length' => 255, 'notnull' => false]);

    return $table;
}

/** @param list<string> $names */
function renamerLive(array $names): array
{
    return array_fill_keys($names, true);
}

// --- the declaration ------------------------------------------------------

it('records nothing until asked', function () {
    $table = renamerTable(new Schema(), 't');

    expect(Renamer::previousTableNames($table))->toBe([]);
    expect(Renamer::previousColumnNames($table))->toBe([]);
});

it('records a former table name and a former column name in one option', function () {
    $table = renamerTable(new Schema(), 'sales_flat_order');
    Renamer::renamed($table, from: 'sales_order', columns: ['customer_email' => 'customer_mail']);

    expect($table->getOption(Renamer::OPTION))->toBe([
        'table' => ['sales_order'],
        'columns' => ['customer_email' => ['customer_mail']],
    ]);
});

it('keeps a rename chain newest-first and ignores a repeated name', function () {
    $table = renamerTable(new Schema(), 'c');
    Renamer::renamed($table, from: 'b');
    Renamer::renamed($table, from: 'a');
    Renamer::renamed($table, from: 'b');

    expect(Renamer::previousTableNames($table))->toBe(['b', 'a']);

    Renamer::renamed($table, columns: ['customer_email' => ['second', 'first']]);
    Renamer::renamed($table, columns: ['customer_email' => 'second']);

    expect(Renamer::previousColumnNames($table))->toBe(['customer_email' => ['second', 'first']]);
});

it('prefixes recorded table names but never column names', function () {
    $table = renamerTable(new Schema(), 'sales_flat_order');
    Renamer::renamed($table, from: 'sales_order', columns: ['customer_email' => 'customer_mail']);

    Renamer::applyPrefix($table, 'pfx_');

    expect(Renamer::previousTableNames($table))->toBe(['pfx_sales_order']);
    expect(Renamer::previousColumnNames($table))->toBe(['customer_email' => ['customer_mail']]);
});

it('leaves the history alone when no prefix is configured', function () {
    $table = renamerTable(new Schema(), 't');
    Renamer::renamed($table, from: 'old');

    Renamer::applyPrefix($table, '');

    expect(Renamer::previousTableNames($table))->toBe(['old']);
});

it('survives the rebuild Collector uses to apply the table prefix', function () {
    $table = renamerTable(new Schema(), 'sales_flat_order');
    Renamer::renamed($table, from: 'sales_order', columns: ['customer_email' => 'customer_mail']);

    // Mirrors Collector::rebuildWithPrefix().
    $rebuilt = $table->edit()
        ->setName(OptionallyQualifiedName::unquoted('pfx_sales_flat_order'))
        ->create();

    expect(Renamer::previousTableNames($rebuilt))->toBe(['sales_order']);
    expect(Renamer::previousColumnNames($rebuilt))->toBe(['customer_email' => ['customer_mail']]);
});

it('refuses a hand-written history that is not the shape renamed() writes', function () {
    $table = renamerTable(new Schema(), 't');
    $table->addOption(Renamer::OPTION, ['table' => 'old']);

    expect(fn() => Renamer::previousTableNames($table))
        ->toThrow(UnsupportedMigrationException::class, 'Renamer::renamed()');
});

it('never reaches the DDL of any supported platform', function () {
    $table = renamerTable(new Schema(), 'sales_flat_order');
    Renamer::renamed($table, from: 'sales_order', columns: ['customer_email' => 'customer_mail']);

    foreach ([new MySQLPlatform(), new PostgreSQLPlatform(), new SQLitePlatform()] as $platform) {
        foreach ($platform->getCreateTableSQL($table) as $statement) {
            expect($statement)->not->toContain(Renamer::OPTION);
            expect($statement)->not->toContain('sales_order');
            expect($statement)->not->toContain('customer_mail');
        }
    }
});

// --- validate() ----------------------------------------------------------

it('accepts a history that names nothing declared', function () {
    $schema = new Schema();
    $table = renamerTable($schema, 'sales_flat_order');
    Renamer::renamed($table, from: 'sales_order', columns: ['customer_email' => 'customer_mail']);

    Renamer::validate($schema);

    expect(Renamer::previousTableNames($schema->getTable('sales_flat_order')))->toBe(['sales_order']);
});

it('refuses a table alias that names another declared table', function () {
    $schema = new Schema();
    renamerTable($schema, 'sales_order');
    Renamer::renamed(renamerTable($schema, 'sales_flat_order'), from: 'sales_order');

    expect(fn() => Renamer::validate($schema))
        ->toThrow(UnsupportedMigrationException::class, 'is itself a declared table');
});

it('refuses two tables that claim the same alias', function () {
    $schema = new Schema();
    Renamer::renamed(renamerTable($schema, 'a'), from: 'shared_old');
    Renamer::renamed(renamerTable($schema, 'b'), from: 'shared_old');

    expect(fn() => Renamer::validate($schema))
        ->toThrow(UnsupportedMigrationException::class, 'Only one table can inherit it');
});

it('refuses a column history that names an undeclared column', function () {
    $schema = new Schema();
    Renamer::renamed(renamerTable($schema, 't'), columns: ['no_such_column' => 'old']);

    expect(fn() => Renamer::validate($schema))
        ->toThrow(UnsupportedMigrationException::class, 'the table declares no such column');
});

it('refuses a column alias that names another declared column', function () {
    $schema = new Schema();
    Renamer::renamed(renamerTable($schema, 't'), columns: ['customer_email' => 'entity_id']);

    expect(fn() => Renamer::validate($schema))
        ->toThrow(UnsupportedMigrationException::class, 'is itself a declared column');
});

it('refuses two columns of one table that claim the same alias', function () {
    $schema = new Schema();
    $table = renamerTable($schema, 't');
    $table->addColumn('other', Types::STRING, ['length' => 8, 'notnull' => false]);
    Renamer::renamed($table, columns: ['customer_email' => 'shared_old']);
    Renamer::renamed($table, columns: ['other' => 'shared_old']);

    expect(fn() => Renamer::validate($schema))
        ->toThrow(UnsupportedMigrationException::class, 'both declare the previous name');
});

// --- planTableRenames() --------------------------------------------------

it('renames a table when only the former name exists', function () {
    $schema = new Schema();
    Renamer::renamed(renamerTable($schema, 'sales_flat_order'), from: 'sales_order');

    $result = Renamer::planTableRenames(new MySQLPlatform(), $schema, renamerLive(['sales_order']));

    expect($result['sql'])->toBe(['ALTER TABLE `sales_order` RENAME TO `sales_flat_order`']);
    expect($result['sources'])->toBe(['sales_flat_order' => 'sales_order']);
});

it('emits the same rename form on every supported platform', function () {
    $schema = new Schema();
    Renamer::renamed(renamerTable($schema, 'new_name'), from: 'old_name');

    foreach ([new MySQLPlatform(), new PostgreSQLPlatform(), new SQLitePlatform()] as $platform) {
        $result = Renamer::planTableRenames($platform, $schema, renamerLive(['old_name']));
        expect($result['sql'])->toHaveCount(1);
        expect($result['sql'][0])->toContain('old_name');
        expect($result['sql'][0])->toContain('RENAME TO');
        expect($result['sql'][0])->toContain('new_name');
    }
});

it('skips a table rename that already ran', function () {
    $schema = new Schema();
    Renamer::renamed(renamerTable($schema, 'sales_flat_order'), from: 'sales_order');

    $result = Renamer::planTableRenames(new MySQLPlatform(), $schema, renamerLive(['sales_flat_order']));

    expect($result['sql'])->toBe([]);
    expect($result['sources'])->toBe([]);
});

it('skips a table rename when neither name exists', function () {
    $schema = new Schema();
    Renamer::renamed(renamerTable($schema, 'sales_flat_order'), from: 'sales_order');

    expect(Renamer::planTableRenames(new MySQLPlatform(), $schema, renamerLive([]))['sql'])->toBe([]);
});

it('refuses a table rename when both names exist', function () {
    $schema = new Schema();
    Renamer::renamed(renamerTable($schema, 'sales_flat_order'), from: 'sales_order');

    expect(fn() => Renamer::planTableRenames(
        new MySQLPlatform(),
        $schema,
        renamerLive(['sales_order', 'sales_flat_order']),
    ))->toThrow(UnsupportedMigrationException::class, 'both tables exist');
});

it('refuses a table rename when two former names exist', function () {
    $schema = new Schema();
    Renamer::renamed(renamerTable($schema, 'c'), from: ['b', 'a']);

    expect(fn() => Renamer::planTableRenames(new MySQLPlatform(), $schema, renamerLive(['a', 'b'])))
        ->toThrow(UnsupportedMigrationException::class, 'all exist');
});

it('matches a former table name case-insensitively but renames the live spelling', function () {
    $schema = new Schema();
    Renamer::renamed(renamerTable($schema, 'sales_flat_order'), from: 'sales_order');

    $result = Renamer::planTableRenames(new MySQLPlatform(), $schema, renamerLive(['Sales_Order']));

    expect($result['sql'])->toBe(['ALTER TABLE `Sales_Order` RENAME TO `sales_flat_order`']);
    expect($result['sources'])->toBe(['sales_flat_order' => 'Sales_Order']);
});

it('refuses a table rename when the live destination differs only in case', function () {
    $schema = new Schema();
    Renamer::renamed(renamerTable($schema, 'sales_flat_order'), from: 'sales_order');

    expect(fn() => Renamer::planTableRenames(
        new MySQLPlatform(),
        $schema,
        renamerLive(['sales_order', 'Sales_Flat_Order']),
    ))->toThrow(UnsupportedMigrationException::class, 'both tables exist');
});

it('follows a rename chain to whichever former name survived', function () {
    $schema = new Schema();
    Renamer::renamed(renamerTable($schema, 'c'), from: ['b', 'a']);

    $result = Renamer::planTableRenames(new MySQLPlatform(), $schema, renamerLive(['a']));

    expect($result['sources'])->toBe(['c' => 'a']);
});

// --- repointForeignKeys() ------------------------------------------------

it('repoints a live foreign key that references a renamed table', function () {
    $live = (new Schema())->createTable('sales_order_item');
    $live->addColumn('order_id', Types::INTEGER, ['unsigned' => true]);
    $live->addForeignKeyConstraint('sales_order', ['order_id'], ['entity_id'], [], 'FK_ORDER');

    $repointed = Renamer::repointForeignKeys($live, ['sales_flat_order' => 'sales_order']);

    $foreignKey = $repointed->getForeignKey('FK_ORDER');
    expect($foreignKey->getReferencedTableName()->getUnqualifiedName()->getValue())->toBe('sales_flat_order');
});

it('leaves a table without foreign keys onto renamed tables untouched', function () {
    $live = (new Schema())->createTable('t');
    $live->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $live->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], [], 'FK_STORE');

    $repointed = Renamer::repointForeignKeys($live, ['sales_flat_order' => 'sales_order']);

    expect($repointed)->toBe($live);
});

// --- renameLiveColumns() -------------------------------------------------

function renamerLiveTable(string $oldColumnName): Table
{
    $table = (new Schema())->createTable('sales_flat_order');
    $table->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $table->addColumn($oldColumnName, Types::STRING, ['length' => 255, 'notnull' => false]);
    $table->addIndex([$oldColumnName], 'IDX_MAIL');
    $table->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames($oldColumnName)->create(),
    );

    return $table;
}

it('renames a live column and moves the primary key and index with it', function () {
    $schema = new Schema();
    $target = renamerTable($schema, 'sales_flat_order');
    Renamer::renamed($target, columns: ['customer_email' => 'customer_mail']);

    $result = Renamer::renameLiveColumns(new MySQLPlatform(), renamerLiveTable('customer_mail'), $target);

    expect($result['sql'])->toBe(
        ['ALTER TABLE `sales_flat_order` RENAME COLUMN `customer_mail` TO `customer_email`'],
    );
    expect($result['live']->hasColumn('customer_email'))->toBeTrue();
    expect($result['live']->hasColumn('customer_mail'))->toBeFalse();

    // Table::renameColumn() would leave the primary key on the old name.
    $primaryKey = $result['live']->getPrimaryKeyConstraint();
    expect($primaryKey)->not->toBeNull();
    expect(array_map(fn($n): string => $n->toString(), $primaryKey->getColumnNames()))->toBe(['customer_email']);
    expect(array_map(
        fn($c): string => $c->getColumnName()->toString(),
        $result['live']->getIndex('IDX_MAIL')->getIndexedColumns(),
    ))->toBe(['customer_email']);
});

it('moves a foreign key with the renamed column', function () {
    $live = (new Schema())->createTable('t');
    $live->addColumn('old_store_id', Types::SMALLINT, ['unsigned' => true]);
    $live->addForeignKeyConstraint('core_store', ['old_store_id'], ['store_id'], [], 'FK_STORE');

    $schema = new Schema();
    $target = $schema->createTable('t');
    $target->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    Renamer::renamed($target, columns: ['store_id' => 'old_store_id']);

    $result = Renamer::renameLiveColumns(new MySQLPlatform(), $live, $target);

    $foreignKey = $result['live']->getForeignKey('FK_STORE');
    expect(array_map(fn($n): string => $n->toString(), $foreignKey->getReferencingColumnNames()))->toBe(['store_id']);
});

it('emits the same column rename form on every supported platform', function () {
    $schema = new Schema();
    $target = renamerTable($schema, 'sales_flat_order');
    Renamer::renamed($target, columns: ['customer_email' => 'customer_mail']);

    foreach ([new MySQLPlatform(), new PostgreSQLPlatform(), new SQLitePlatform()] as $platform) {
        $result = Renamer::renameLiveColumns($platform, renamerLiveTable('customer_mail'), $target);
        expect($result['sql'])->toHaveCount(1);
        expect($result['sql'][0])->toContain('RENAME COLUMN');
        expect($result['sql'][0])->toContain('customer_mail');
        expect($result['sql'][0])->toContain('customer_email');
    }
});

it('skips a column rename that already ran', function () {
    $schema = new Schema();
    $target = renamerTable($schema, 'sales_flat_order');
    Renamer::renamed($target, columns: ['customer_email' => 'customer_mail']);

    $result = Renamer::renameLiveColumns(new MySQLPlatform(), renamerLiveTable('customer_email'), $target);

    expect($result['sql'])->toBe([]);
    expect($result['live']->hasColumn('customer_email'))->toBeTrue();
});

it('skips a column rename when neither name exists', function () {
    $schema = new Schema();
    $target = renamerTable($schema, 'sales_flat_order');
    Renamer::renamed($target, columns: ['customer_email' => 'customer_mail']);

    $live = (new Schema())->createTable('sales_flat_order');
    $live->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);

    expect(Renamer::renameLiveColumns(new MySQLPlatform(), $live, $target)['sql'])->toBe([]);
});

it('refuses a column rename when both columns exist', function () {
    $schema = new Schema();
    $target = renamerTable($schema, 'sales_flat_order');
    Renamer::renamed($target, columns: ['customer_email' => 'customer_mail']);

    $live = renamerLiveTable('customer_mail');
    $live->addColumn('customer_email', Types::STRING, ['length' => 255, 'notnull' => false]);

    expect(fn() => Renamer::renameLiveColumns(new MySQLPlatform(), $live, $target))
        ->toThrow(UnsupportedMigrationException::class, 'both columns exist');
});
