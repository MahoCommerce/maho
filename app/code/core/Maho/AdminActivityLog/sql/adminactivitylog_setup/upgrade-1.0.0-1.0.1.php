<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
