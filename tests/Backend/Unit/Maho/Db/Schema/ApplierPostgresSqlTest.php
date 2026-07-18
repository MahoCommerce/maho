<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema\Applier;

/**
 * Guards the regex-based post-processors that patch DBAL's emitted PostgreSQL
 * DDL. These parse generated SQL strings, so a DBAL output-format change could
 * silently no-op them; these tests pin the expected transforms independently of
 * the full-matrix schema-migration workflow.
 */

/** @param list<mixed> $args */
function invokeApplier(string $method, array $args): mixed
{
    $ref = new ReflectionMethod(Applier::class, $method);
    return $ref->invoke(null, ...$args);
}

function pgTable(string $name): Table
{
    return (new Schema())->createTable($name);
}

it('quotes a digit-leading legacy index name in ALTER INDEX ... RENAME', function () {
    $platform = new PostgreSQLPlatform();
    $statements = ['ALTER INDEX 0abc123 RENAME TO "IDX_NEW"'];

    $result = invokeApplier('quotePostgresRenameIndexNames', [$platform, $statements]);

    // The old (unquoted, digit-leading) name must be quoted, else Postgres
    // rejects it as a syntax error; the new name is already quoted by DBAL.
    expect($result[0])->toBe('ALTER INDEX "0abc123" RENAME TO "IDX_NEW"');
});

it('leaves a non-rename statement untouched', function () {
    $platform = new PostgreSQLPlatform();
    $statements = ['CREATE INDEX foo ON bar (baz)'];

    $result = invokeApplier('quotePostgresRenameIndexNames', [$platform, $statements]);

    expect($result[0])->toBe('CREATE INDEX foo ON bar (baz)');
});

it('appends USING NULL for a bytea type change', function () {
    $platform = new PostgreSQLPlatform();

    $live = pgTable('log_visitor_info');
    $live->addColumn('remote_addr', Types::BIGINT, ['notnull' => false]);
    $target = pgTable('log_visitor_info');
    $target->addColumn('remote_addr', Types::BLOB, ['notnull' => false]);

    $statements = ['ALTER TABLE "log_visitor_info" ALTER "remote_addr" TYPE BYTEA'];
    $result = invokeApplier('fixPostgresColumnTypeChanges', [$platform, [$live], [$target], $statements]);

    expect($result)->toContain('ALTER TABLE "log_visitor_info" ALTER "remote_addr" TYPE BYTEA USING NULL');
});

it('casts through integer and re-sets the default for a boolean to smallint change', function () {
    $platform = new PostgreSQLPlatform();

    $live = pgTable('t');
    $live->addColumn('flag', Types::BOOLEAN, ['default' => false]);
    $target = pgTable('t');
    $target->addColumn('flag', Types::SMALLINT, ['default' => 0]);

    $statements = ['ALTER TABLE "t" ALTER "flag" TYPE SMALLINT'];
    $result = invokeApplier('fixPostgresColumnTypeChanges', [$platform, [$live], [$target], $statements]);

    // Stale boolean default dropped, type cast through integer, target default re-set.
    expect($result)->toBe([
        'ALTER TABLE "t" ALTER "flag" DROP DEFAULT',
        'ALTER TABLE "t" ALTER "flag" TYPE SMALLINT USING "flag"::integer',
        'ALTER TABLE "t" ALTER "flag" SET DEFAULT 0',
    ]);
});

it('passes through a type change that has an implicit cast', function () {
    $platform = new PostgreSQLPlatform();

    $live = pgTable('t');
    $live->addColumn('amount', Types::FLOAT, ['notnull' => false]);
    $target = pgTable('t');
    $target->addColumn('amount', Types::FLOAT, ['notnull' => false]);

    $statements = ['ALTER TABLE "t" ALTER "amount" TYPE DOUBLE PRECISION'];
    $result = invokeApplier('fixPostgresColumnTypeChanges', [$platform, [$live], [$target], $statements]);

    expect($result)->toBe(['ALTER TABLE "t" ALTER "amount" TYPE DOUBLE PRECISION']);
});
