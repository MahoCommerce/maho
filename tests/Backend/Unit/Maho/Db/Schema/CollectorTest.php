<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Maho\Db\Schema\Collector;

/**
 * Unit coverage for the pure, bootstrap-free Collector paths: the storage-engine
 * coercion that keeps a schema author from reintroducing a table MySQL 8.4+
 * refuses to write inside a transaction (SQLSTATE 1785).
 */

/** @param list<mixed> $args */
function invokeCollectorMethod(string $method, array $args): mixed
{
    $ref = new ReflectionMethod(Collector::class, $method);
    return $ref->invoke(null, ...$args);
}

it('reports and overrides a declared non-InnoDB engine', function () {
    $schema = new Schema();
    $schema->createTable('legacy_nonce')->addOption('engine', 'MyISAM');
    $schema->createTable('legacy_tmp')->addOption('engine', 'MEMORY');

    $overridden = invokeCollectorMethod('enforceInnoDbEngine', [$schema]);

    expect($overridden)->toBe(['legacy_nonce' => 'MyISAM', 'legacy_tmp' => 'MEMORY']);
    expect($schema->getTable('legacy_nonce')->getOption('engine'))->toBe('InnoDB');
    expect($schema->getTable('legacy_tmp')->getOption('engine'))->toBe('InnoDB');
});

it('pins InnoDB on a table that declares no engine, and reports nothing', function () {
    $schema = new Schema();
    $schema->createTable('plain');

    $overridden = invokeCollectorMethod('enforceInnoDbEngine', [$schema]);

    // Without the option DBAL emits no ENGINE clause at all, so the table would
    // silently inherit @@default_storage_engine.
    expect($overridden)->toBe([]);
    expect($schema->getTable('plain')->getOption('engine'))->toBe('InnoDB');
});

it('does not report a table that already declares InnoDB in any casing', function () {
    $schema = new Schema();
    $schema->createTable('already')->addOption('engine', 'innodb');

    expect(invokeCollectorMethod('enforceInnoDbEngine', [$schema]))->toBe([]);
    expect($schema->getTable('already')->getOption('engine'))->toBe('InnoDB');
});
