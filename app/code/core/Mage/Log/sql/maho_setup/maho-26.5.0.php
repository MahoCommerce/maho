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
    $columns = [
        ['log/customer',       'login_at',       true,  null,                                     'Login Time'],
        ['log/customer',       'logout_at',      true,  null,                                     'Logout Time'],
        ['log/quote_table',    'created_at',     false, Maho\Db\Ddl\Table::TIMESTAMP_INIT,        'Creation Time'],
        ['log/quote_table',    'deleted_at',     true,  null,                                     'Deletion Time'],
        ['log/summary_table',  'add_date',       true,  null,                                     'Date'],
        ['log/url_table',      'visit_time',     true,  null,                                     'Visit Time'],
        ['log/visitor',        'first_visit_at', true,  null,                                     'First Visit Time'],
        ['log/visitor',        'last_visit_at',  true,  null,                                     'Last Visit Time'],
        ['log/visitor_online', 'first_visit_at', true,  null,                                     'First Visit Time'],
        ['log/visitor_online', 'last_visit_at',  true,  null,                                     'Last Visit Time'],
    ];

    foreach ($columns as [$table, $column, $nullable, $default, $comment]) {
        $installer->getConnection()->modifyColumn(
            $installer->getTable($table),
            $column,
            [
                'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
                'nullable' => $nullable,
                'default'  => $default,
                'comment'  => $comment,
            ],
        );
    }
}

$installer->endSetup();
