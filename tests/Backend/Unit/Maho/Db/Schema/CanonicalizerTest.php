<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema\Canonicalizer;

/**
 * Unit coverage for the introspection-vs-declarative reconciliation that keeps
 * the DBAL Comparator from emitting representation-only churn. Pure DBAL Schema
 * objects, no Maho bootstrap or database.
 */

/** Build a one-table Schema and return the Table for mutation. */
function canonTable(string $name): Table
{
    return (new Schema())->createTable($name);
}

it('preserves an undeclared live column by merging it into the target', function () {
    $live = canonTable('t');
    $live->addColumn('id', Types::INTEGER, ['unsigned' => true]);
    $live->addColumn('custom_col', Types::STRING, ['length' => 64, 'notnull' => false]);

    $target = canonTable('t');
    $target->addColumn('id', Types::INTEGER, ['unsigned' => true]);

    Canonicalizer::reconcile($live, $target, []);

    expect($target->hasColumn('custom_col'))->toBeTrue();
    expect($target->getColumn('custom_col')->getLength())->toBe(64);
});

it('does not drop or add a managed column that exists on both sides', function () {
    $live = canonTable('t');
    $live->addColumn('id', Types::INTEGER, ['unsigned' => true]);
    $target = canonTable('t');
    $target->addColumn('id', Types::INTEGER, ['unsigned' => true]);

    Canonicalizer::reconcile($live, $target, []);

    expect($target->getColumns())->toHaveCount(1);
});

it('strips column comments on both live and target', function () {
    $live = canonTable('t');
    $live->addColumn('id', Types::INTEGER, ['comment' => 'live comment']);
    $target = canonTable('t');
    $target->addColumn('id', Types::INTEGER, ['comment' => 'target comment']);

    Canonicalizer::reconcile($live, $target, []);

    expect($live->getColumn('id')->getComment())->toBe('');
    expect($target->getColumn('id')->getComment())->toBe('');
});

it('reconciles a numeric default spelled differently when the types match', function () {
    $live = canonTable('t');
    $live->addColumn('flag', Types::INTEGER, ['default' => '1']);
    $target = canonTable('t');
    $target->addColumn('flag', Types::INTEGER, ['default' => 1]);

    Canonicalizer::reconcile($live, $target, []);

    // Live adopts the target's representation, so the Comparator sees no diff.
    expect($live->getColumn('flag')->getDefault())->toBe(1);
});

it('reconciles a CURRENT_TIMESTAMP string default against the expression object', function () {
    $live = canonTable('t');
    $live->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $target = canonTable('t');
    $target->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);

    Canonicalizer::reconcile($live, $target, []);

    expect($live->getColumn('created_at')->getDefault())->toBeInstanceOf(CurrentTimestamp::class);
});

it('leaves a genuine default change untouched (null on one side)', function () {
    $live = canonTable('t');
    $live->addColumn('val', Types::INTEGER, ['notnull' => false, 'default' => null]);
    $target = canonTable('t');
    $target->addColumn('val', Types::INTEGER, ['default' => 0]);

    Canonicalizer::reconcile($live, $target, []);

    // A null vs a value is a real change; the Comparator must still emit it.
    expect($live->getColumn('val')->getDefault())->toBeNull();
});

it('aligns a structurally-identical live index name to the target name', function () {
    $live = canonTable('t');
    $live->addColumn('code', Types::STRING, ['length' => 32]);
    $live->addIndex(['code'], 'LEGACY_HASH_NAME');

    $target = canonTable('t');
    $target->addColumn('code', Types::STRING, ['length' => 32]);
    $target->addIndex(['code'], 'IDX_TARGET_NAME');

    // Both index names are "physical" so neither is treated as a phantom.
    Canonicalizer::reconcile($live, $target, ['LEGACY_HASH_NAME']);

    expect($live->hasIndex('IDX_TARGET_NAME'))->toBeTrue();
    expect($live->hasIndex('LEGACY_HASH_NAME'))->toBeFalse();
});

it('drops a phantom index that has no physical counterpart', function () {
    $live = canonTable('t');
    $live->addColumn('a', Types::INTEGER, ['unsigned' => true]);
    $live->addColumn('b', Types::INTEGER, ['unsigned' => true]);
    $live->addIndex(['a'], 'real_idx');
    $live->addIndex(['b'], 'phantom_idx');

    $target = canonTable('t');
    $target->addColumn('a', Types::INTEGER, ['unsigned' => true]);
    $target->addColumn('b', Types::INTEGER, ['unsigned' => true]);

    // Only real_idx physically exists; phantom_idx was synthesized by introspection.
    Canonicalizer::reconcile($live, $target, ['real_idx']);

    expect($live->hasIndex('real_idx'))->toBeTrue();
    expect($live->hasIndex('phantom_idx'))->toBeFalse();
});

it('aligns a legacy SERIAL column to the target identity form', function () {
    $live = canonTable('t');
    $live->addColumn('id', Types::INTEGER, [
        'unsigned' => true,
        'autoincrement' => false,
        'default' => "nextval('t_id_seq'::regclass)",
    ]);

    $target = canonTable('t');
    $target->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);

    Canonicalizer::reconcile($live, $target, []);

    expect($live->getColumn('id')->getAutoincrement())->toBeTrue();
    expect($live->getColumn('id')->getDefault())->toBeNull();
});

it('aligns the target table charset to a utf8/utf8mb3 synonym so the diff converges', function () {
    // A legacy install reports its tables as 'utf8mb3'; the declarative target
    // carries the historical alias 'utf8'. They are the same physical charset, so
    // the option strings must be aligned or the Comparator re-emits a no-op CHANGE
    // forever for every undeclared column merged onto the table.
    $live = canonTable('t');
    $live->addColumn('id', Types::INTEGER, ['unsigned' => true]);
    $live->addOption('charset', 'utf8mb3');
    $live->addOption('collation', 'utf8mb3_general_ci');

    $target = canonTable('t');
    $target->addColumn('id', Types::INTEGER, ['unsigned' => true]);
    $target->addOption('charset', 'utf8');
    $target->addOption('collation', 'utf8_general_ci');

    Canonicalizer::reconcile($live, $target, []);

    expect($target->getOption('charset'))->toBe('utf8mb3');
    expect($target->getOption('collation'))->toBe('utf8mb3_general_ci');
});

it('keeps a genuine table charset migration (utf8mb3 to utf8mb4)', function () {
    $live = canonTable('t');
    $live->addColumn('id', Types::INTEGER, ['unsigned' => true]);
    $live->addOption('charset', 'utf8mb3');
    $live->addOption('collation', 'utf8mb3_general_ci');

    $target = canonTable('t');
    $target->addColumn('id', Types::INTEGER, ['unsigned' => true]);
    $target->addOption('charset', 'utf8mb4');
    $target->addOption('collation', 'utf8mb4_general_ci');

    Canonicalizer::reconcile($live, $target, []);

    // A real charset change must survive — not aligned away.
    expect($target->getOption('charset'))->toBe('utf8mb4');
    expect($target->getOption('collation'))->toBe('utf8mb4_general_ci');
});
