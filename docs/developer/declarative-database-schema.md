---
description: Define a Maho module's tables in one declarative sql/schema.php file and let Doctrine DBAL reconcile the database, replacing chains of install and upgrade scripts.
---

# Declarative Database Schema <span class="version-badge">v26.7+</span>

Maho can build and maintain a module's database tables from a single declarative file, `sql/schema.php`, instead of a growing chain of imperative install and upgrade scripts. The file describes the *target* shape of your tables, and Maho reconciles the live database with that target automatically, using [Doctrine DBAL](database-layer.md)'s own schema `Comparator`.

This is the same idea Magento 2 introduced with `db_schema.xml`, expressed with DBAL's native `Schema` objects so there is no separate XML or array format to learn or maintain.

## Overview

With the traditional [setup resources](guide/setup-resources.md) system, every schema change means bumping a module version and writing a new `upgrade-X.Y.Z-A.B.C.php` with the exact `ALTER` statements. Over time this becomes a long, hard-to-review history of incremental changes, and the current shape of a table can only be reconstructed by reading every script in order.

The declarative system replaces that with a single file per module that always describes the table as it should be *now*:

- **One source of truth** - the file reflects the current target structure, not a diff history.
- **Easy to review** - a column or index change is a one-line edit, not a new migration script.
- **Engine-agnostic** - you declare intent (types, indexes, foreign keys); Maho emits the correct DDL for MySQL/MariaDB, PostgreSQL, and SQLite.
- **Safe in-place migration** - existing installs are reconciled with native `ALTER`s (or a table rebuild on SQLite), idempotently, landing on a structure byte-identical to a fresh install.
- **No reinvention** - it is built directly on DBAL's `Schema`, `Comparator`, and `Table` primitives.

Schema (table structure) becomes declarative. **Data work stays imperative**, see [Relationship to setup resources](#relationship-to-setup-resources).

## The `sql/schema.php` file

Each module may ship an optional `sql/schema.php` that returns a closure receiving a shared DBAL `SchemaEditor`. The closure adds (or augments) tables on that schema:

```php
<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cron
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('cron_schedule')
            ->addColumn(Schema::column('schedule_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('job_code', Types::STRING, length: 255, default: '0'))
            ->addColumn(Schema::column('status', Types::STRING, length: 7, default: 'pending'))
            ->addColumn(Schema::column('messages', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_IMMUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('scheduled_at', Types::DATETIME_IMMUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('schedule_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('job_code'))
            ->setComment('Cron Schedule')
            ->create(),
    );
};
```

`Maho\Db\Schema::column()` takes the name, the type, and named arguments for the setters of the DBAL `ColumnEditor`. Do not use the DBAL `Schema`, `Table` or `Column` mutators, such as `createTable()` and `Table::addColumn()`: DBAL 4.5 deprecates them.

The file lives in `sql/`, alongside any existing imperative `sql/<resource>_setup/` scripts. Maho discovers it by convention, no configuration is required.

### Conventions

Follow these conventions so your schema matches the core modules and migrates cleanly:

- **Unprefixed table names.** Author tables with their bare names (`cron_schedule`, not `prefix_cron_schedule`). Maho applies the configured `table_prefix` from `app/etc/local.xml` centrally.
- **No vendor prefix in an identifier.** A table takes the name of its module or of its domain, never the vendor name: `blog_post_entity`, `feedmanager_feed`, `paypal_webhook_event`. The same rule applies to store-config sections, cron ids and observer ids. Core modules are checked by `tests/Backend/Unit/Maho/NamingConventionTest.php`; third-party modules are exempt, so your own install never fails `composer test`. A former name recorded with `Schema::renamed()` is the one exception, because it must stay verbatim.
- **`notNull` defaults to `true`.** DBAL columns are `NOT NULL` by default, so you only spell out `notNull: false` for nullable columns. This makes the nullable columns stand out.
- **Use the immutable date and time types**: `Types::DATE_IMMUTABLE`, `Types::DATETIME_IMMUTABLE` and `Types::TIME_IMMUTABLE`. DBAL 4.6 deprecates the mutable types. Both produce the same DDL, so a module that changes from one to the other needs no migration.
- **No zero-date defaults.** Never use `'0000-00-00 00:00:00'`. It is not strict-mode safe and cannot be stored on PostgreSQL. Use a nullable date/datetime column (`notNull: false`) and let real `NULL` represent "no date".
- **Use `CurrentTimestamp` for `CURRENT_TIMESTAMP` defaults**, not the literal string:
  ```php
  use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
  ->addColumn(Schema::column('created_at', Types::DATETIME_IMMUTABLE, default: new CurrentTimestamp()))
  ```
- **Do not name indexes or foreign keys.** Do not call `setUnquotedName()` on an `Index::editor()` or a `ForeignKeyConstraint::editor()`. DBAL names each index, and Maho names each foreign key. This keeps you within the 64-character identifier limit and makes each name unique. Pass the `Index::editor()` itself to `addIndex()`, not its `create()`. Set the type for a unique or a fulltext index: `Index::editor()->setType(IndexType::FULLTEXT)->setUnquotedColumnNames('data_index')`.
- **Charset and collation** default to `utf8mb4` / `utf8mb4_general_ci`, the same as the legacy adapter. To override them for one table, call `->setOptions(['charset' => ..., 'collation' => ...])` on its `Table::editor()`.
- **No runtime dependencies.** A `schema.php` must parse with no `Mage::app()` bootstrap, no database connection, and no engine-aware helpers. Keep it pure structure.

### Foreign keys

Declare a foreign key with the referenced table's *unprefixed* name, the local column(s), and the referenced column(s). Do not pass a constraint name:

```php
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;

->addForeignKeyConstraint(
    ForeignKeyConstraint::editor()
        ->setUnquotedReferencingColumnNames('entity_id')               // local column(s)
        ->setUnquotedReferencedTableName('catalog_product_entity')     // referenced table (unprefixed)
        ->setUnquotedReferencedColumnNames('entity_id')                // referenced column(s)
        ->setOnUpdateAction(ReferentialAction::CASCADE)
        ->setOnDeleteAction(ReferentialAction::CASCADE)
        ->create(),
)
```

Make sure the foreign-key column's type matches the referenced column exactly (same integer width, the same `unsigned` flag), or the database will reject the constraint.

### Cross-module tables and foreign keys

The `SchemaEditor` passed to your closure is **shared across all modules**, and modules are processed in dependency (`depends_on`) order. That means you can reference, or even extend, a table another module declared, as long as your module declares a dependency on it.

A foreign key names the table of the other module, as shown above. To add columns, indexes or foreign keys to a table of another module, change it with `$schema->modifyTableByUnquotedName()`:

```php
use Doctrine\DBAL\Schema\TableEditor;

return function (SchemaEditor $schema): void {
    // weee_tax is owned by this module. Its foreign key on entity_id
    // references catalog_product_entity, a table owned by Mage_Catalog.
    $schema->addTable(Table::editor()->setUnquotedName('weee_tax') /* ... */ ->create());

    // Graft WEEE columns onto a flat sales table owned by Mage_Sales
    $schema->modifyTableByUnquotedName('sales_flat_order_item', static function (TableEditor $table): void {
        $table->addColumn(Schema::column('weee_tax_applied_amount', Types::DECIMAL, precision: 12, scale: 4, notNull: false));
    });
};
```

!!! warning "Declare your dependencies"
    `$schema->modifyTableByUnquotedName('sales_flat_order_item', ...)` throws if `Mage_Sales` has not been processed yet. Whenever you reference or extend another module's table, declare a `<depends>` on that module in `app/etc/modules/<Your_Module>.xml`. The dependency is what guarantees the other module's `schema.php` runs first.

## Renaming a table or a column <span class="version-badge">v26.9+</span>

A schema comparison is structural, so it cannot tell a rename from a drop plus an add. Without help, a rename leaves the old object holding every row and creates the new one empty.

Declare the identity history on the table that the rename produced, and give the former names newest-first:

```php
Table::editor()
    ->setUnquotedName('sales_flat_order')
    ->setOptions(Schema::renamed(from: 'sales_order', columns: ['customer_email' => 'customer_mail']))
```

Both `from:` and each value of `columns:` take a single name or a list of names, newest-first. A column that was renamed `a` to `b` to `c` therefore declares `['b', 'a']`:

```php
->setOptions(Schema::renamed(columns: ['c' => ['b', 'a']]))
```

`./maho migrate` renames the live objects **before** it compares anything, so the comparator, the PostgreSQL fixups and the SQLite rebuild all see the end state. The statement order becomes: conversions, renames, creates, alters. `--dry-run` lists renames as non-destructive.

### How an entry is resolved

Each entry carries its own precondition instead of a position in an ordered list, so it is self-idempotent and needs no version counter and no state table:

| Old name in the database | New name in the database | Result |
|---|---|---|
| present | absent | The rename runs. |
| absent | present | Skipped, the rename already ran. |
| absent | absent | Skipped. |
| present | present | Refused with guidance. Only a human can decide which one holds the real rows. |

A fresh install ignores the history completely, because no old name exists.

### Rules and lifetime

- **A former name must be unique.** If another table or another column also declares it, the schema is rejected at collect time. This also rules out a rename cycle.
- **Drop the entries** once upgrades from the release that carried the old name are no longer supported.

## How and when it runs

The declarative schema is applied automatically, you do not call it yourself, in two places:

- `./maho install` (during `Mage_Install_Model_Installer::installDb()`)
- `./maho migrate`

In both, the order is:

```
1. Declarative schema   create/alter tables from every sql/schema.php
2. Legacy schema scripts  sql/<resource>_setup/install-*.php, upgrade-*.php
3. Data scripts           data/<resource>_setup/data-*.php
```

The declarative phase runs **first** so that any legacy script which inserts or updates rows finds its tables already in place.

## The `./maho migrate` command

`./maho migrate` applies all three phases above. The declarative phase is idempotent: a second run detects no structural difference and applies zero statements.

### Previewing changes with `--dry-run`

```bash
./maho migrate --dry-run
```

This prints the exact SQL the declarative phase would run without touching the database, and lists which setup scripts would run. Destructive statements (dropping an index or foreign key) are highlighted, so you can see them before they execute. Add `-v` to see the raw SQL.

```bash
./maho migrate --dry-run -v
```

### Destructive changes

When a migration would drop an index or foreign key (see [What the migration preserves, and what it normalizes](#what-the-migration-preserves-and-what-it-normalizes)), `./maho migrate` prints a warning listing those statements, then applies them. The migration always runs to completion, it never stops to ask, so unattended upgrades (deploy scripts, CI, the backend upgrade flow) behave exactly as they always have. Use `--dry-run` first if you want to review the dropped objects before they are applied.

## In-place migration

`./maho migrate` reconciles an existing (legacy or already-declarative) database with the target schema, in place, on every supported engine:

- **MySQL / MariaDB / PostgreSQL** are reconciled with native `ALTER`s (rename, type change, column/index/foreign-key add or drop).
- **SQLite** cannot `ALTER` a column type or add a foreign key, so any table that needs a structural change is rebuilt: its rows are copied to a temporary table, the table is recreated straight from the declarative target (primary key, foreign keys, and every index included), and the data is copied back. Tables that already match emit nothing.

Foreign-key enforcement is suspended for the whole batch, and on PostgreSQL and SQLite the batch runs inside a transaction, so a failure mid-migration rolls back cleanly.

The migration is designed so that a legacy install reconciled with `./maho migrate` lands on a schema **byte-identical to a fresh declarative install**, and a second run is a no-op.

## What the migration preserves, and what it normalizes

The migration treats data and derivable structure differently:

- **Columns are preserved.** A column a third-party module or a DBA added to a managed (core) table survives migration with its data untouched. The migration never drops a column it does not manage, because a column holds data and dropping it is irreversible loss.
- **Whole undeclared tables are left untouched.** The migration only ever considers the tables that some module declares.

But:

- **Undeclared indexes and foreign keys are removed.** The migration normalizes a managed table to its declared structure, so an index or foreign key that no `schema.php` declares is dropped. This is intentional: it cleans up the redundant and obsolete indexes/foreign keys that years of legacy upgrade scripts accumulated (for example a unique index the declarative schema turned into the primary key, or an index that duplicated another), and it is what lets a migrated database land byte-identical to a fresh install. Indexes and foreign keys carry no data, so removing one is not destructive the way dropping a column is, a dropped index is a rebuildable performance object, a dropped foreign key is a re-addable constraint.

### Keeping a custom index or foreign key

If you have a custom index or foreign key on a managed table that you want to keep, **declare it in a module's own `sql/schema.php`**. Once it is part of the declared target it is treated exactly like a core object and is never removed. Anything not declared is treated as legacy structure to normalize away.

Run `./maho migrate --dry-run` before migrating to see exactly which indexes and foreign keys would be removed.

## Relationship to setup resources

The declarative schema replaces only the *structure* part of [setup resources](guide/setup-resources.md). Data work stays imperative, split by when it needs to run:

| File | Purpose | Phase |
|------|---------|-------|
| `sql/schema.php` | Table structure (columns, indexes, foreign keys) | Declarative |
| `sql/<resource>_setup/install-*.php`, `upgrade-*.php` | Foundational data other modules' installs depend on | Legacy schema scripts |
| `data/<resource>_setup/data-*.php` | Application data (country lists, config seeds, EAV attributes) | Data scripts |

**Existing third-party modules keep working unchanged.** A module with only the classic imperative scripts and no `sql/schema.php` is migrated exactly as before. Adopting the declarative schema is opt-in, per module.

### Module versions

With structure declarative, a module's `<version>` no longer tracks schema state, it only gates the remaining *data* scripts. To change a table, you edit `sql/schema.php` directly; there is no version bump and no new migration file for a structural change. You still bump the version when you add a new `data-*.php` script.

## Authoring a schema for your own module

1. Create `app/code/<...>/<Your_Module>/sql/schema.php` returning a closure as shown above.
2. Declare `<depends>` on any module whose tables you reference or extend.
3. Run `./maho migrate --dry-run` to review the SQL, then `./maho migrate` to apply it.
4. For application data (seed rows, config, EAV attributes), add a `data/<resource>_setup/data-*.php` script and bump your module version.

To change the schema later, edit `sql/schema.php` and run `./maho migrate` again. The reconciler figures out the difference.

## Known behaviors and limitations

- **A new `NOT NULL` column with no default on a populated table is refused.** Existing rows have no value to put in it. Give the column a default, or make it nullable. This is the only migration that any engine refuses outright.
- **Legacy zero-date values are converted to `NULL`.** Date/datetime columns that older installs stored as the `'0000-00-00 00:00:00'` sentinel are made nullable by the declarative schema, and existing sentinel rows are set to real `NULL` during migration (on MySQL/MariaDB/SQLite; PostgreSQL cannot hold the sentinel, so there is nothing to clean).
- **Some report aggregation tables now require `order_status NOT NULL`.** A store that already holds aggregation rows with a `NULL` `order_status` should clear the affected aggregation table and reindex its reports before migrating. These are derived tables, no source data is lost.
- **PostgreSQL legacy `bigint` IP columns lose their values.** A few nullable IP-tracking columns move from `bigint` (`ip2long`) to a binary type with no faithful cast, so existing values are discarded on PostgreSQL. MySQL/MariaDB are unaffected.

See also the [Database Layer](database-layer.md) documentation for the underlying Doctrine DBAL adapter, query builder, and multi-engine support.
