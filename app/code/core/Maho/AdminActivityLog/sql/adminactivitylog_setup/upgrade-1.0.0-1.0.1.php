<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AdminActivityLog
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$activityTable = $installer->getTable('adminactivitylog/activity');
$consumerTable = $installer->getTable('oauth/consumer');

// Add consumer_id column for API-based actions
if (!$connection->tableColumnExists($activityTable, 'consumer_id')) {
    $connection->addColumn($activityTable, 'consumer_id', [
        'type' => Maho\Db\Ddl\Table::TYPE_INTEGER,
        'unsigned' => true,
        'nullable' => true,
        'after' => 'user_id',
        'comment' => 'OAuth Consumer ID (for API actions)',
    ]);

    $connection->addIndex(
        $activityTable,
        $installer->getIdxName('adminactivitylog/activity', ['consumer_id']),
        ['consumer_id'],
    );

    $connection->addForeignKey(
        $installer->getFkName('adminactivitylog/activity', 'consumer_id', 'oauth/consumer', 'entity_id'),
        $activityTable,
        'consumer_id',
        $consumerTable,
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_SET_NULL,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    );
}

$installer->endSetup();
