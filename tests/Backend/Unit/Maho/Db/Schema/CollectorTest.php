<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema\Collector;
use Maho\Db\Schema as MahoSchema;
use Maho\Db\Schema\Renamer;

/**
 * Unit coverage for the pure, bootstrap-free Collector paths: how a schema.php
 * closure runs, and the final form each declared table takes (FK names, table
 * prefix, charset, storage engine).
 */

function declareCollectorClosure(callable $closure, SchemaEditor $editor): void
{
    $ref = new ReflectionMethod(Collector::class, 'declare');
    $ref->invoke(null, $closure, $editor);
}

function finalizeCollectorTable(Table $table, string $prefix = ''): Table
{
    $ref = new ReflectionMethod(Collector::class, 'finalizeTable');
    return $ref->invoke(null, $table, $prefix);
}

function collectorTable(string $name): TableEditor
{
    return Table::editor()
        ->setUnquotedName($name)
        ->addColumn(
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
        );
}

/** The cms_block_store table as Mage_Cms declares it. */
function collectorBlockStore(): Table
{
    return Table::editor()
        ->setUnquotedName('cms_block_store')
        ->addColumn(
            Column::editor()
                ->setUnquotedName('block_id')
                ->setTypeName(Types::SMALLINT)
                ->create(),
        )
        ->addColumn(
            Column::editor()
                ->setUnquotedName('store_id')
                ->setTypeName(Types::SMALLINT)
                ->setUnsigned(true)
                ->create(),
        )
        ->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('block_id', 'store_id')
                ->create(),
        )
        ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
        ->addForeignKeyConstraint(
            ForeignKeyConstraint::editor()
                ->setUnquotedReferencingColumnNames('block_id')
                ->setUnquotedReferencedTableName('cms_block')
                ->setUnquotedReferencedColumnNames('block_id')
                ->setOnUpdateAction(ReferentialAction::CASCADE)
                ->setOnDeleteAction(ReferentialAction::CASCADE)
                ->create(),
        )
        ->create();
}

it('gives an unnamed foreign key the name Table::addForeignKeyConstraint() generated', function () {
    // Pinned from an install made before the port to the DBAL editors.
    $table = finalizeCollectorTable(collectorBlockStore());

    expect($table->hasForeignKey('FK_A5A72945E9ED820C'))->toBeTrue();
    expect($table->getForeignKey('FK_A5A72945E9ED820C')->getObjectName()?->toString())->toBe('FK_A5A72945E9ED820C');
    expect($table->hasIndex('IDX_A5A72945B092A811'))->toBeTrue();
});

it('keeps the unprefixed FK and index names under a table prefix', function () {
    $table = finalizeCollectorTable(collectorBlockStore(), 'pfx_');

    expect($table->getObjectName()->getUnqualifiedName()->getValue())->toBe('pfx_cms_block_store');
    expect($table->hasForeignKey('FK_A5A72945E9ED820C'))->toBeTrue();
    expect($table->getForeignKey('FK_A5A72945E9ED820C')->getReferencedTableName()->getUnqualifiedName()->getValue())
        ->toBe('pfx_cms_block');
    expect($table->hasIndex('IDX_A5A72945B092A811'))->toBeTrue();
});

it('prefixes the recorded former table names', function () {
    $table = finalizeCollectorTable(
        collectorTable('api_idempotency_key')->setOptions(MahoSchema::renamed(from: 'maho_api_idempotency_keys'))->create(),
        'pfx_',
    );

    expect(Renamer::previousTableNames($table))->toBe(['pfx_maho_api_idempotency_keys']);
});

it('gives a table utf8mb4 when it declares no charset, and keeps an explicit one', function () {
    $default = finalizeCollectorTable(collectorTable('plain')->create());
    $declared = finalizeCollectorTable(collectorTable('own')->setOptions(['charset' => 'latin1'])->create());

    expect($default->getOption('charset'))->toBe('utf8mb4');
    expect($default->getOption('collation'))->toBe('utf8mb4_general_ci');
    expect($declared->getOption('charset'))->toBe('latin1');
    expect($declared->getOption('collation'))->toBe('utf8mb4_general_ci');
});

it('overrides a declared non-InnoDB engine', function () {
    $table = finalizeCollectorTable(collectorTable('legacy_nonce')->setOptions(['engine' => 'MyISAM'])->create());

    expect($table->getOption('engine'))->toBe('InnoDB');
});

it('pins InnoDB on a table that declares no engine', function () {
    // Without the option DBAL emits no ENGINE clause at all, so the table would
    // silently inherit @@default_storage_engine.
    expect(finalizeCollectorTable(collectorTable('plain')->create())->getOption('engine'))->toBe('InnoDB');
});

it('keeps the table comment', function () {
    $table = finalizeCollectorTable(collectorTable('t')->setComment('A comment')->create());

    expect($table->getComment())->toBe('A comment');
});

it('gives a SchemaEditor to a closure that asks for one', function () {
    $editor = Schema::editor();

    declareCollectorClosure(function (SchemaEditor $schema): void {
        $schema->addTable(collectorTable('fresh')->create());
    }, $editor);

    expect($editor->create()->hasTable('fresh'))->toBeTrue();
});

it('still runs a schema.php written for the Schema mutators', function () {
    $editor = Schema::editor()->addTable(collectorTable('core_table')->create());

    // A third-party module written before DBAL 4.5.
    declareCollectorClosure(function (Schema $schema): void {
        $schema->createTable('legacy_table')->addColumn('id', Types::INTEGER);
        $schema->getTable('core_table')->addColumn('legacy_flag', Types::SMALLINT);
    }, $editor);

    $schema = $editor->create();
    expect($schema->hasTable('legacy_table'))->toBeTrue();
    expect($schema->getTable('core_table')->hasColumn('legacy_flag'))->toBeTrue();
});
