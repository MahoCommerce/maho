<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Reports
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
        ['reports/event',                  'logged_at', true,  null,                                     'Logged At'],
        ['reports/compared_product_index', 'added_at',  false, Maho\Db\Ddl\Table::TIMESTAMP_INIT,        'Added At'],
        ['reports/viewed_product_index',   'added_at',  false, Maho\Db\Ddl\Table::TIMESTAMP_INIT,        'Added At'],
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
