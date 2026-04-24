<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Replace DBAL's implicit `DEFAULT '0000-00-00 00:00:00'` (emitted for TIMESTAMP NOT NULL
// columns without an explicit default) with CURRENT_TIMESTAMP. MySQL-only: PgSQL/SQLite
// never emit the zero-date sentinel. See issue #857.
if ($installer->getConnection() instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    $columns = [
        ['sales/billing_agreement',   'created_at',     'Created At'],
        ['sales/order_item',          'created_at',     'Created At'],
        ['sales/order_item',          'updated_at',     'Updated At'],
        ['sales/quote',               'created_at',     'Created At'],
        ['sales/quote',               'updated_at',     'Updated At'],
        ['sales/quote_address',       'created_at',     'Created At'],
        ['sales/quote_address',       'updated_at',     'Updated At'],
        ['sales/quote_address_item',  'created_at',     'Created At'],
        ['sales/quote_address_item',  'updated_at',     'Updated At'],
        ['sales/quote_item',          'created_at',     'Created At'],
        ['sales/quote_item',          'updated_at',     'Updated At'],
        ['sales/quote_payment',       'created_at',     'Created At'],
        ['sales/quote_payment',       'updated_at',     'Updated At'],
        ['sales/quote_address_shipping_rate', 'created_at',     'Created At'],
        ['sales/quote_address_shipping_rate', 'updated_at',     'Updated At'],
        ['sales/recurring_profile',   'created_at',     'Created At'],
        ['sales/recurring_profile',   'start_datetime', 'Start Datetime'],
    ];

    foreach ($columns as [$table, $column, $comment]) {
        $installer->getConnection()->modifyColumn(
            $installer->getTable($table),
            $column,
            [
                'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
                'nullable' => false,
                'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
                'comment'  => $comment,
            ],
        );
    }
}

$installer->endSetup();
