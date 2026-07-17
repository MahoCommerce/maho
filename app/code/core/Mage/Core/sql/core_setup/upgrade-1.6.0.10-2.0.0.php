<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

// Drop the legacy persistent-cart table and quote flag (both safe if already absent).
$connection->dropTable($installer->getTable('persistent_session'));
$connection->dropColumn($installer->getTable('sales/quote'), 'is_persistent');

// Drop the orphaned maho_version column left by the retired maho_setup mechanism.
// Absent on fresh installs (no longer declared in sql/schema.php), present on
// migrated stores; dropColumn is a no-op when it doesn't exist, so both converge.
$connection->dropColumn($installer->getTable('core/resource'), 'maho_version');

// Convert every physical TIMESTAMP column to DATETIME for cross-engine consistency and to
// unblock surgical modifyColumn via DBAL (Doctrine's column model conflates TIMESTAMP and
// DATETIME, silently rewriting TIMESTAMP→DATETIME on diff). DATETIME has the same
// CURRENT_TIMESTAMP/ON UPDATE features on MySQL 5.6+ and MariaDB 10.0+, with broader year
// range (1000-9999 vs 1970-2038) and no implicit timezone conversion. Maho already forces
// session TZ to UTC on connect (#861), making TIMESTAMP's TZ magic a no-op.
//
// Default-handling strategy:
//   - DEFAULT CURRENT_TIMESTAMP is preserved (auto-populates created_at-style columns).
//     Detected without regex: a leading "'" can't appear in a real datetime expression, so
//     "doesn't start with quote" + "contains current_timestamp" identifies it on both
//     MariaDB ("current_timestamp()") and MySQL 8 ("CURRENT_TIMESTAMP").
//   - Original nullability is preserved. A NOT NULL column stays NOT NULL with no DEFAULT
//     (modern sql_mode rejects legacy '0000-00-00 00:00:00' sentinels as DATETIME defaults,
//     and Mage_Core_Model_Abstract::_beforeSave populates timestamp fields anyway). Forcing
//     it nullable would fight a NOT NULL declarative target.
//   - A nullable column with any other default becomes NULL DEFAULT NULL.
//   - ON UPDATE CURRENT_TIMESTAMP is preserved where present.
//
// PgSQL/SQLite have no TIMESTAMP/DATETIME distinction, so this is a MySQL-only conversion.
// Virtual/stored generated columns can't be retyped via plain MODIFY COLUMN — would require
// dropping and recomputing the GENERATED expression, which we can't do generically here. Skip
// them; the surgical modifyColumn path also refuses them via _assertColumnIsSafeToModify. The
// filter targets only real generated columns ("VIRTUAL GENERATED"/"STORED GENERATED"); MySQL 8
// also tags any expression default (e.g. DEFAULT CURRENT_TIMESTAMP) as "DEFAULT_GENERATED",
// and those must still be converted, so a blanket "%GENERATED%" exclusion would wrongly skip
// every created_at-style column.
if ($connection instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    $columns = $connection->fetchAll(
        'SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT, EXTRA '
        . 'FROM INFORMATION_SCHEMA.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = \'timestamp\' '
        . 'AND EXTRA NOT LIKE \'%VIRTUAL GENERATED%\' '
        . 'AND EXTRA NOT LIKE \'%STORED GENERATED%\' '
        . 'ORDER BY TABLE_NAME, ORDINAL_POSITION',
    );

    foreach ($columns as $col) {
        $rawDefault = $col['COLUMN_DEFAULT'];
        $isCurrentTimestamp = is_string($rawDefault)
            && $rawDefault !== ''
            && $rawDefault[0] !== "'"
            && stripos($rawDefault, 'current_timestamp') !== false;
        $notNull = strtoupper((string) $col['IS_NULLABLE']) === 'NO';

        $clauses = ['DATETIME'];
        if ($isCurrentTimestamp) {
            $clauses[] = $notNull ? 'NOT NULL' : 'NULL';
            $clauses[] = 'DEFAULT CURRENT_TIMESTAMP';
        } elseif ($notNull) {
            $clauses[] = 'NOT NULL';
        } else {
            $clauses[] = 'NULL DEFAULT NULL';
        }
        if (str_contains(strtoupper((string) ($col['EXTRA'] ?? '')), 'ON UPDATE CURRENT_TIMESTAMP')) {
            $clauses[] = 'ON UPDATE CURRENT_TIMESTAMP';
        }
        if ($col['COLUMN_COMMENT'] !== '') {
            $clauses[] = sprintf('COMMENT %s', $connection->quote($col['COLUMN_COMMENT']));
        }

        $connection->raw_query(sprintf(
            'ALTER TABLE %s MODIFY COLUMN %s %s',
            $connection->quoteIdentifier((string) $col['TABLE_NAME']),
            $connection->quoteIdentifier((string) $col['COLUMN_NAME']),
            implode(' ', $clauses),
        ));
    }
}

$installer->endSetup();
