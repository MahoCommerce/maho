<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Replace DBAL's implicit `DEFAULT '0000-00-00 00:00:00'` / MySQL's implicit
// `ON UPDATE CURRENT_TIMESTAMP` on TIMESTAMP columns with explicit defaults.
// MySQL-only: PgSQL/SQLite never emit either. See issue #857.
if ($installer->getConnection() instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    // Pairs of [tableAlias, columnName, partial-modifyColumn definition].
    // Most columns become NULLABLE with NULL default; quote_table.created_at keeps NOT NULL
    // and re-asserts CURRENT_TIMESTAMP (the partial drops the implicit ON UPDATE clause).
    $columns = [
        ['log/customer',       'login_at',       ['nullable' => true, 'default' => null]],
        ['log/customer',       'logout_at',      ['nullable' => true, 'default' => null]],
        ['log/quote_table',    'created_at',     ['default' => Maho\Db\Ddl\Table::TIMESTAMP_INIT]],
        ['log/quote_table',    'deleted_at',     ['nullable' => true, 'default' => null]],
        ['log/summary_table',  'add_date',       ['nullable' => true, 'default' => null]],
        ['log/url_table',      'visit_time',     ['nullable' => true, 'default' => null]],
        ['log/visitor',        'first_visit_at', ['nullable' => true, 'default' => null]],
        ['log/visitor',        'last_visit_at',  ['nullable' => true, 'default' => null]],
        ['log/visitor_online', 'first_visit_at', ['nullable' => true, 'default' => null]],
        ['log/visitor_online', 'last_visit_at',  ['nullable' => true, 'default' => null]],
    ];

    foreach ($columns as [$table, $column, $definition]) {
        $installer->getConnection()->modifyColumn(
            $installer->getTable($table),
            $column,
            $definition,
        );
    }
}

$installer->endSetup();
