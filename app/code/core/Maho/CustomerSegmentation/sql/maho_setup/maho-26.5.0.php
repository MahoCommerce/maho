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
    $tables = [
        'customersegmentation/segment',
        'customersegmentation/segment_customer',
        'customersegmentation/emailSequence',
    ];
    foreach ($tables as $alias) {
        $table = $installer->getTable($alias);
        if (!$installer->getConnection()->isTableExists($table)) {
            continue;
        }
        $installer->getConnection()->modifyColumn(
            $table,
            'updated_at',
            [
                'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
                'nullable' => false,
                'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
                'comment'  => 'Updated At',
            ],
        );
    }

    $segmentTable = $installer->getTable('customersegmentation/segment');
    if ($installer->getConnection()->isTableExists($segmentTable)) {
        $installer->getConnection()->modifyColumn(
            $segmentTable,
            'last_refresh_at',
            [
                'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
                'nullable' => true,
                'default'  => null,
                'comment'  => 'Last Refresh Time',
            ],
        );
    }

    $progressTable = $installer->getTable('customer_segment_sequence_progress');
    if ($installer->getConnection()->isTableExists($progressTable)) {
        $installer->getConnection()->modifyColumn(
            $progressTable,
            'scheduled_at',
            [
                'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
                'nullable' => true,
                'default'  => null,
                'comment'  => 'Scheduled Send Time',
            ],
        );
        $installer->getConnection()->modifyColumn(
            $progressTable,
            'sent_at',
            [
                'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
                'nullable' => true,
                'default'  => null,
                'comment'  => 'Actual Send Time',
            ],
        );
    }
}

$installer->endSetup();
