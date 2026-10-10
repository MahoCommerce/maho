<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Db\Schema\Collector;
use Tests\MahoBackendTestCase;

/**
 * MySQL refuses a table whose autoincrement column is not the first column of a key
 * (error 1075). PostgreSQL and SQLite accept it, so only an install on MySQL fails.
 */

uses(MahoBackendTestCase::class);

it('declares every autoincrement column as the first column of a key', function () {
    [$schema] = Collector::collect();
    $offenders = [];

    foreach ($schema->getTables() as $table) {
        $firstKeyColumns = [];
        $primaryKey = $table->getPrimaryKeyConstraint();
        if ($primaryKey !== null) {
            $firstKeyColumns[] = $primaryKey->getColumnNames()[0]->toString();
        }
        foreach ($table->getIndexes() as $index) {
            $firstKeyColumns[] = $index->getIndexedColumns()[0]->getColumnName()->toString();
        }
        $firstKeyColumns = array_map(static fn(string $name): string => strtolower(trim($name, '"`')), $firstKeyColumns);

        foreach ($table->getColumns() as $column) {
            $name = strtolower($column->getObjectName()->toString());
            if ($column->getAutoincrement() && !in_array($name, $firstKeyColumns, true)) {
                $offenders[] = sprintf('%s.%s', $table->getObjectName()->toString(), $name);
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", $offenders));
});
