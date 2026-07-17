<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db\Schema;

use RuntimeException;

/**
 * Thrown when a declared change can't be applied to an existing database
 * without losing or inventing data, so it needs the author's intervention
 * rather than an automatic migration.
 *
 * The case that arises in practice is adding a NOT NULL column with no default
 * to a table that already holds rows: no engine can backfill a value, so the
 * fix is to give the column a default or make it nullable. (SQLite reaches this
 * via the table-rebuild path in Applier::sqliteRebuildTable; MySQL/MariaDB and
 * Postgres reject the equivalent ALTER directly.)
 */
final class UnsupportedMigrationException extends RuntimeException {}
