<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema\Canonicalizer;

/**
 * Unit coverage for the introspection-vs-declarative reconciliation that keeps
 * the DBAL Comparator from emitting representation-only churn. Pure DBAL Schema
 * objects, no Maho bootstrap or database.
 */

function canonTable(string $name): TableEditor
{
    return Table::editor()->setUnquotedName($name);
}

function canonColumn(string $name, string $type): ColumnEditor
{
    return Column::editor()->setUnquotedName($name)->setTypeName($type);
}

it('preserves an undeclared live column by merging it into the target', function () {
    $live = canonTable('t')
        ->addColumn(canonColumn('id', Types::INTEGER)->setUnsigned(true)->create())
        ->addColumn(canonColumn('custom_col', Types::STRING)->setLength(64)->setNotNull(false)->create())
        ->create();
    $target = canonTable('t')
        ->addColumn(canonColumn('id', Types::INTEGER)->setUnsigned(true)->create())
        ->create();

    [, $target] = Canonicalizer::reconcile($live, $target, []);

    expect($target->hasColumn('custom_col'))->toBeTrue();
    expect($target->getColumn('custom_col')->getLength())->toBe(64);
});

it('does not drop or add a managed column that exists on both sides', function () {
    $live = canonTable('t')
        ->addColumn(canonColumn('id', Types::INTEGER)->setUnsigned(true)->create())
        ->create();
    $target = canonTable('t')
        ->addColumn(canonColumn('id', Types::INTEGER)->setUnsigned(true)->create())
        ->create();

    [, $target] = Canonicalizer::reconcile($live, $target, []);

    expect($target->getColumns())->toHaveCount(1);
});

it('strips column comments on both live and target', function () {
    $live = canonTable('t')
        ->addColumn(canonColumn('id', Types::INTEGER)->setComment('live comment')->create())
        ->create();
    $target = canonTable('t')
        ->addColumn(canonColumn('id', Types::INTEGER)->setComment('target comment')->create())
        ->create();

    [$live, $target] = Canonicalizer::reconcile($live, $target, []);

    expect($live->getColumn('id')->getComment())->toBe('');
    expect($target->getColumn('id')->getComment())->toBe('');
});

it('reconciles a numeric default spelled differently when the types match', function () {
    $live = canonTable('t')
        ->addColumn(canonColumn('flag', Types::INTEGER)->setDefaultValue('1')->create())
        ->create();
    $target = canonTable('t')
        ->addColumn(canonColumn('flag', Types::INTEGER)->setDefaultValue(1)->create())
        ->create();

    [$live] = Canonicalizer::reconcile($live, $target, []);

    // Live adopts the target's representation, so the Comparator sees no diff.
    expect($live->getColumn('flag')->getDefault())->toBe(1);
});

it('reconciles a CURRENT_TIMESTAMP string default against the expression object', function () {
    $live = canonTable('t')
        ->addColumn(canonColumn('created_at', Types::DATETIME_MUTABLE)->setDefaultValue('CURRENT_TIMESTAMP')->create())
        ->create();
    $target = canonTable('t')
        ->addColumn(canonColumn('created_at', Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
        ->create();

    [$live] = Canonicalizer::reconcile($live, $target, []);

    expect($live->getColumn('created_at')->getDefault())->toBeInstanceOf(CurrentTimestamp::class);
});

it('leaves a genuine default change untouched (null on one side)', function () {
    $live = canonTable('t')
        ->addColumn(canonColumn('val', Types::INTEGER)->setNotNull(false)->setDefaultValue(null)->create())
        ->create();
    $target = canonTable('t')
        ->addColumn(canonColumn('val', Types::INTEGER)->setDefaultValue(0)->create())
        ->create();

    [$live] = Canonicalizer::reconcile($live, $target, []);

    // A null vs a value is a real change; the Comparator must still emit it.
    expect($live->getColumn('val')->getDefault())->toBeNull();
});

it('aligns the float precision and scale MySQL reports to the declared ones', function () {
    $live = canonTable('t')
        ->addColumn(canonColumn('weight', Types::FLOAT)->setPrecision(22)->setScale(0)->create())
        ->create();
    $target = canonTable('t')
        ->addColumn(canonColumn('weight', Types::FLOAT)->create())
        ->create();

    [$live] = Canonicalizer::reconcile($live, $target, []);

    expect($live->getColumn('weight')->getPrecision())->toBe($target->getColumn('weight')->getPrecision());
    expect($live->getColumn('weight')->getScale())->toBe($target->getColumn('weight')->getScale());
});

it('aligns a structurally-identical live index name to the target name', function () {
    $live = canonTable('t')
        ->addColumn(canonColumn('code', Types::STRING)->setLength(32)->create())
        ->addIndex(Index::editor()->setUnquotedName('LEGACY_HASH_NAME')->setUnquotedColumnNames('code')->create())
        ->create();
    $target = canonTable('t')
        ->addColumn(canonColumn('code', Types::STRING)->setLength(32)->create())
        ->addIndex(Index::editor()->setUnquotedName('IDX_TARGET_NAME')->setUnquotedColumnNames('code')->create())
        ->create();

    // Both index names are "physical" so neither is treated as a phantom.
    [$live] = Canonicalizer::reconcile($live, $target, ['LEGACY_HASH_NAME']);

    expect($live->hasIndex('IDX_TARGET_NAME'))->toBeTrue();
    expect($live->hasIndex('LEGACY_HASH_NAME'))->toBeFalse();
});

it('folds the drop of a quoted live index and the add of its bare target into one MySQL statement', function () {
    // What introspection produces: index name and column names marked quoted.
    $live = canonTable('t')
        ->addColumn(canonColumn('customer_id', Types::INTEGER)->setUnsigned(true)->create())
        ->addColumn(canonColumn('product_id', Types::INTEGER)->setUnsigned(true)->create())
        ->addIndex(Index::editor()
            ->setQuotedName('UNQ_LEGACY')
            ->setType(IndexType::UNIQUE)
            ->setQuotedColumnNames('customer_id', 'product_id')
            ->create())
        ->create();
    $target = canonTable('t')
        ->addColumn(canonColumn('customer_id', Types::INTEGER)->setUnsigned(true)->create())
        ->addColumn(canonColumn('product_id', Types::INTEGER)->setUnsigned(true)->create())
        ->addIndex(
            Index::editor()
                ->setUnquotedName('IDX_TARGET')
                ->setUnquotedColumnNames('customer_id', 'product_id')
                ->create(),
        )
        ->create();

    [$live, $target] = Canonicalizer::reconcile($live, $target, ['"UNQ_LEGACY"']);

    // MySQL refuses a lone DROP INDEX on the index that backs a foreign key (error 1553).
    $platform = new MySQLPlatform();
    $sql = $platform->getAlterTableSQL((new Comparator($platform))->compareTables($live, $target));

    expect($sql)->toBe([
        'ALTER TABLE t DROP INDEX UNQ_LEGACY, ADD INDEX IDX_TARGET (customer_id, product_id)',
    ]);
});

it('drops a phantom index that has no physical counterpart', function () {
    $live = canonTable('t')
        ->addColumn(canonColumn('a', Types::INTEGER)->setUnsigned(true)->create())
        ->addColumn(canonColumn('b', Types::INTEGER)->setUnsigned(true)->create())
        ->addIndex(Index::editor()->setUnquotedName('real_idx')->setUnquotedColumnNames('a')->create())
        ->addIndex(Index::editor()->setUnquotedName('phantom_idx')->setUnquotedColumnNames('b')->create())
        ->create();
    $target = canonTable('t')
        ->addColumn(canonColumn('a', Types::INTEGER)->setUnsigned(true)->create())
        ->addColumn(canonColumn('b', Types::INTEGER)->setUnsigned(true)->create())
        ->create();

    // Only real_idx physically exists; phantom_idx was synthesized by introspection.
    [$live] = Canonicalizer::reconcile($live, $target, ['real_idx']);

    expect($live->hasIndex('real_idx'))->toBeTrue();
    expect($live->hasIndex('phantom_idx'))->toBeFalse();
});

it('drops the phantom index DBAL synthesizes for a foreign key, and plans the target index', function () {
    $foreignKey = ForeignKeyConstraint::editor()
        ->setUnquotedName('FK_PARENT')
        ->setUnquotedReferencingColumnNames('parent_id')
        ->setUnquotedReferencedTableName('parent')
        ->setUnquotedReferencedColumnNames('id')
        ->create();
    $table = static fn() => canonTable('t')
        ->addColumn(canonColumn('id', Types::INTEGER)->setUnsigned(true)->create())
        ->addColumn(canonColumn('parent_id', Types::INTEGER)->setUnsigned(true)->create())
        ->addForeignKeyConstraint($foreignKey)
        ->create();
    $live = $table();
    $target = $table();
    $phantom = array_values(array_map(static fn(Index $index): string => $index->getObjectName()->toString(), $live->getIndexes()));
    expect($phantom)->toHaveCount(1);

    // Introspection reports no physical index: the implicit one is a phantom.
    [$live, $target] = Canonicalizer::reconcile($live, $target, []);

    expect($live->hasIndex($phantom[0]))->toBeFalse();
    $platform = new MySQLPlatform();
    $sql = $platform->getAlterTableSQL((new Comparator($platform))->compareTables($live, $target));
    expect($sql)->toBe(["CREATE INDEX {$phantom[0]} ON t (parent_id)"]);
});

it('aligns a legacy SERIAL column to the target identity form', function () {
    $live = canonTable('t')
        ->addColumn(canonColumn('id', Types::INTEGER)
            ->setUnsigned(true)
            ->setAutoincrement(false)
            ->setDefaultValue("nextval('t_id_seq'::regclass)")
            ->create())
        ->create();
    $target = canonTable('t')
        ->addColumn(canonColumn('id', Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
        ->create();

    [$live] = Canonicalizer::reconcile($live, $target, []);

    expect($live->getColumn('id')->getAutoincrement())->toBeTrue();
    expect($live->getColumn('id')->getDefault())->toBeNull();
});

it('aligns the target table charset to a utf8/utf8mb3 synonym so the diff converges', function () {
    // A legacy install reports its tables as 'utf8mb3'; the declarative target
    // carries the historical alias 'utf8'. They are the same physical charset, so
    // the option strings must be aligned or the Comparator re-emits a no-op CHANGE
    // forever for every undeclared column merged onto the table.
    $live = canonTable('t')
        ->addColumn(canonColumn('id', Types::INTEGER)->setUnsigned(true)->create())
        ->setOptions(['charset' => 'utf8mb3', 'collation' => 'utf8mb3_general_ci'])
        ->create();
    $target = canonTable('t')
        ->addColumn(canonColumn('id', Types::INTEGER)->setUnsigned(true)->create())
        ->setOptions(['charset' => 'utf8', 'collation' => 'utf8_general_ci', 'engine' => 'InnoDB'])
        ->setComment('Kept')
        ->create();

    [, $target] = Canonicalizer::reconcile($live, $target, []);

    expect($target->getOption('charset'))->toBe('utf8mb3');
    expect($target->getOption('collation'))->toBe('utf8mb3_general_ci');
    expect($target->getOption('engine'))->toBe('InnoDB');
    expect($target->getComment())->toBe('Kept');
});

it('keeps a genuine table charset migration (utf8mb3 to utf8mb4)', function () {
    $live = canonTable('t')
        ->addColumn(canonColumn('id', Types::INTEGER)->setUnsigned(true)->create())
        ->setOptions(['charset' => 'utf8mb3', 'collation' => 'utf8mb3_general_ci'])
        ->create();
    $target = canonTable('t')
        ->addColumn(canonColumn('id', Types::INTEGER)->setUnsigned(true)->create())
        ->setOptions(['charset' => 'utf8mb4', 'collation' => 'utf8mb4_general_ci'])
        ->create();

    [, $target] = Canonicalizer::reconcile($live, $target, []);

    // A real charset change must survive — not aligned away.
    expect($target->getOption('charset'))->toBe('utf8mb4');
    expect($target->getOption('collation'))->toBe('utf8mb4_general_ci');
});
