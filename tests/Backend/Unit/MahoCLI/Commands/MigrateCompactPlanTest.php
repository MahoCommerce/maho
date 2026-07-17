<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use MahoCLI\Commands\Migrate;

/**
 * Regression coverage for the `migrate --dry-run` compact plan formatter.
 * MySQL/MariaDB backtick-quote every identifier, so the statement-flattening
 * regexes must read backtick- and double-quoted names alike (and must capture
 * the whole name, not just the first character). Pure string formatting, no DB.
 */

function compactStatement(string $sql): array
{
    static $ref = null;
    if ($ref === null) {
        $ref = new ReflectionMethod(Migrate::class, 'compactStatement');
    }
    return $ref->invoke(new Migrate('migrate'), $sql);
}

it('renders a backtick-quoted DROP INDEX with its full name and flags it destructive', function () {
    $r = compactStatement('DROP INDEX `IDX_CUSTOMER_ENTITY_EMAIL_WEBSITE_ID` ON customer_entity');
    expect($r['text'])->toBe('drop index IDX_CUSTOMER_ENTITY_EMAIL_WEBSITE_ID on customer_entity');
    expect($r['destructive'])->toBeTrue();
});

it('renders a double-quoted DROP INDEX (Postgres form) with its full name', function () {
    $r = compactStatement('DROP INDEX "idx_some_legacy_name"');
    expect($r['text'])->toBe('drop index idx_some_legacy_name');
    expect($r['destructive'])->toBeTrue();
});

it('renders a backtick-quoted CREATE INDEX as non-destructive with columns', function () {
    $r = compactStatement('CREATE INDEX `IDX_X` ON `tbl` (col_a, col_b)');
    expect($r['text'])->toBe('add index IDX_X on tbl (col_a, col_b)');
    expect($r['destructive'])->toBeFalse();
});

it('labels a unique index as such', function () {
    $r = compactStatement('CREATE UNIQUE INDEX `UNQ_X` ON `tbl` (col_a)');
    expect($r['text'])->toBe('add unique index UNQ_X on tbl (col_a)');
    expect($r['destructive'])->toBeFalse();
});

it('renders CREATE TABLE as non-destructive', function () {
    $r = compactStatement('CREATE TABLE `maho_ai_task` (id INT)');
    expect($r['text'])->toBe('create table maho_ai_task');
    expect($r['destructive'])->toBeFalse();
});

it('flags an ALTER that drops a foreign key as destructive', function () {
    $r = compactStatement('ALTER TABLE `weee_tax` DROP FOREIGN KEY `FK_WEEE`');
    expect($r['text'])->toStartWith('alter weee_tax:');
    expect($r['destructive'])->toBeTrue();
});

it('treats an additive ALTER as non-destructive', function () {
    $r = compactStatement('ALTER TABLE `catalog_product_link` ADD rule_id INT UNSIGNED DEFAULT NULL');
    expect($r['text'])->toStartWith('alter catalog_product_link:');
    expect($r['destructive'])->toBeFalse();
});
