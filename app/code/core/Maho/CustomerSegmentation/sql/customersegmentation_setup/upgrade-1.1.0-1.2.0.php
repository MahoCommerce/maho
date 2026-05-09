<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Drop MySQL-only `ON UPDATE CURRENT_TIMESTAMP` clause on updated_at columns that were originally
// declared with TIMESTAMP_INIT_UPDATE (#856), and force explicit DEFAULT on TYPE_TIMESTAMP columns
// declared without one so MySQL's `explicit_defaults_for_timestamp = OFF` cannot silently inject
// `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` (#857).
if ($installer->getConnection() instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    $columns = [
        ['customersegmentation/segment',          'updated_at',      false, Maho\Db\Ddl\Table::TIMESTAMP_INIT, 'Updated At'],
        ['customersegmentation/segment_customer', 'updated_at',      false, Maho\Db\Ddl\Table::TIMESTAMP_INIT, 'Updated At'],
        ['customersegmentation/emailSequence',    'updated_at',      false, Maho\Db\Ddl\Table::TIMESTAMP_INIT, 'Updated At'],
        ['customersegmentation/segment',          'last_refresh_at', true,  null,                              'Last Refresh Time'],
        ['customer_segment_sequence_progress',    'scheduled_at',    true,  null,                              'Scheduled Send Time'],
        ['customer_segment_sequence_progress',    'sent_at',         true,  null,                              'Actual Send Time'],
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
