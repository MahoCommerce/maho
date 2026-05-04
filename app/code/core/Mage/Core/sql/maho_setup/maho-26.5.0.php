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

// Drop MySQL-only `ON UPDATE CURRENT_TIMESTAMP` clause on timestamp columns — value is now
// managed via PHP _beforeSave for cross-engine parity. `core/flag.last_update` was originally
// declared with TIMESTAMP_INIT_UPDATE (#856); `core/config_data.updated_at` was added via
// upgrade-1.6.0.8-1.6.0.9 without options, which on MySQL receives the implicit
// `ON UPDATE CURRENT_TIMESTAMP` injection (#857).
if ($connection instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    $connection->modifyColumn(
        $installer->getTable('core/flag'),
        'last_update',
        ['default' => Maho\Db\Ddl\Table::TIMESTAMP_INIT],
    );
    $connection->modifyColumn(
        $installer->getTable('core/config_data'),
        'updated_at',
        ['default' => Maho\Db\Ddl\Table::TIMESTAMP_INIT],
    );
}

// Convert all physical TIMESTAMP columns to DATETIME for cross-engine consistency and to
// unblock surgical modifyColumn via DBAL (Doctrine's column model conflates TIMESTAMP and
// DATETIME, silently rewriting TIMESTAMP→DATETIME on diff). DATETIME has the same
// CURRENT_TIMESTAMP/ON UPDATE features on MySQL 5.6+ and MariaDB 10.0+, with broader
// year range (1000-9999 vs 1970-2038) and no implicit timezone conversion. Maho already
// forces session TZ to UTC on connect (#861), making TIMESTAMP's TZ magic a no-op.
//
// PgSQL/SQLite have no TIMESTAMP/DATETIME distinction, so this is a MySQL-only conversion.
// Each column is converted via raw ALTER preserving its current default, nullability, and
// comment — read straight from INFORMATION_SCHEMA so we don't have to enumerate columns
// per module.
if ($connection instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    // Generated columns can't be retyped via plain MODIFY COLUMN — would require dropping
    // and recomputing the GENERATED expression, which we can't do generically here. Skip
    // them; the surgical modifyColumn path also refuses them via _assertColumnIsSafeToModify.
    $columns = $connection->fetchAll(
        'SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT, EXTRA '
        . 'FROM INFORMATION_SCHEMA.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = \'timestamp\' '
        . 'AND EXTRA NOT LIKE \'%GENERATED%\' '
        . 'ORDER BY TABLE_NAME, ORDINAL_POSITION',
    );

    foreach ($columns as $col) {
        $tableName = (string) $col['TABLE_NAME'];
        $columnName = (string) $col['COLUMN_NAME'];
        $isNullable = strtoupper((string) $col['IS_NULLABLE']) === 'YES';
        $rawDefault = $col['COLUMN_DEFAULT'];
        $extra = strtoupper((string) ($col['EXTRA'] ?? ''));
        $comment = (string) ($col['COLUMN_COMMENT'] ?? '');

        // Build the DEFAULT clause. CURRENT_TIMESTAMP must remain unquoted; literal
        // values get quoted; NULL gets explicit DEFAULT NULL when the column is
        // nullable; otherwise no DEFAULT clause (NOT NULL with no default).
        $defaultClause = '';
        if (is_string($rawDefault)
            && preg_match('/^current_timestamp(\s*\(\s*\d*\s*\))?$/i', trim($rawDefault)) === 1
        ) {
            $defaultClause = 'DEFAULT CURRENT_TIMESTAMP';
        } elseif ($rawDefault !== null) {
            $defaultClause = sprintf('DEFAULT %s', $connection->quote($rawDefault));
        } elseif ($isNullable) {
            $defaultClause = 'DEFAULT NULL';
        }

        // ON UPDATE CURRENT_TIMESTAMP is preserved if present — DATETIME supports it on
        // MySQL 5.6+ / MariaDB 10.0+. The TIMESTAMP_INIT_UPDATE deprecation is orthogonal:
        // we don't strip the clause here, just migrate the storage type.
        $onUpdateClause = str_contains($extra, 'ON UPDATE CURRENT_TIMESTAMP')
            ? 'ON UPDATE CURRENT_TIMESTAMP'
            : '';

        $commentClause = $comment !== ''
            ? sprintf('COMMENT %s', $connection->quote($comment))
            : '';

        $alterSql = sprintf(
            'ALTER TABLE %s MODIFY COLUMN %s DATETIME %s %s %s %s',
            $connection->quoteIdentifier($tableName),
            $connection->quoteIdentifier($columnName),
            $isNullable ? 'NULL' : 'NOT NULL',
            $defaultClause,
            $onUpdateClause,
            $commentClause,
        );
        $connection->raw_query(trim(preg_replace('/\s+/', ' ', $alterSql)));
    }
}

$installer->endSetup();
