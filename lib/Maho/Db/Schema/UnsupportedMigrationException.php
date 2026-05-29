<?php

/**
 * Maho
 *
 * @package    Maho_Db
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Maho\Db\Schema;

use RuntimeException;

/**
 * Thrown when the declarative schema cannot be reconciled in place on the
 * current platform and the only safe remedy is a fresh install.
 *
 * In practice this is SQLite-only: it has no real column types (everything is
 * INTEGER/TEXT affinity), so bringing an older install up to the declarative
 * schema means changing column types, which SQLite can only do by rebuilding
 * the whole table. DBAL's SQLite rebuild silently drops foreign keys and
 * indexes, so the migration never converges. SQLite is a fully supported
 * engine for running Maho, but in-place schema upgrades on it are not
 * supported: the remedy is a fresh install, or running on MySQL/MariaDB or
 * PostgreSQL, where these are ordinary in-place ALTERs.
 */
final class UnsupportedMigrationException extends RuntimeException {}
