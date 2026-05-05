<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

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
//   - Every other default is dropped (column becomes NULL DEFAULT NULL). That covers
//     legacy '0000-00-00 00:00:00' sentinels which modern sql_mode rejects as DATETIME
//     defaults, plus rare literal datetime defaults — Mage_Core_Model_Abstract::_beforeSave
//     populates timestamp fields anyway, so the DB-level default is redundant for those.
//     Existing row values are preserved by ALTER regardless.
//   - ON UPDATE CURRENT_TIMESTAMP is preserved where present.
//
// PgSQL/SQLite have no TIMESTAMP/DATETIME distinction, so this is a MySQL-only conversion.
// Generated columns can't be retyped via plain MODIFY COLUMN — would require dropping and
// recomputing the GENERATED expression, which we can't do generically here. Skip them; the
// surgical modifyColumn path also refuses them via _assertColumnIsSafeToModify.
if ($connection instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    $columns = $connection->fetchAll(
        'SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT, EXTRA '
        . 'FROM INFORMATION_SCHEMA.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = \'timestamp\' '
        . 'AND EXTRA NOT LIKE \'%GENERATED%\' '
        . 'ORDER BY TABLE_NAME, ORDINAL_POSITION',
    );

    foreach ($columns as $col) {
        $rawDefault = $col['COLUMN_DEFAULT'];
        $isCurrentTimestamp = is_string($rawDefault)
            && $rawDefault !== ''
            && $rawDefault[0] !== "'"
            && stripos($rawDefault, 'current_timestamp') !== false;

        $clauses = ['DATETIME'];
        if ($isCurrentTimestamp) {
            $clauses[] = strtoupper((string) $col['IS_NULLABLE']) === 'NO' ? 'NOT NULL' : 'NULL';
            $clauses[] = 'DEFAULT CURRENT_TIMESTAMP';
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
