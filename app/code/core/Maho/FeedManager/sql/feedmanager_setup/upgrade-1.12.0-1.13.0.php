<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$tableName = $installer->getTable('feedmanager/feed');

// Add notification_mode column
if (!$connection->tableColumnExists($tableName, 'notification_mode')) {
    $connection->addColumn($tableName, 'notification_mode', [
        'type' => Maho\Db\Ddl\Table::TYPE_VARCHAR,
        'length' => 20,
        'nullable' => false,
        'default' => 'none',
        'comment' => 'Notification Mode (none, email, admin, both)',
    ]);
}

// Add notification_frequency column
if (!$connection->tableColumnExists($tableName, 'notification_frequency')) {
    $connection->addColumn($tableName, 'notification_frequency', [
        'type' => Maho\Db\Ddl\Table::TYPE_VARCHAR,
        'length' => 20,
        'nullable' => false,
        'default' => 'once_until_success',
        'comment' => 'Notification Frequency (always, once_until_success)',
    ]);
}

// Add notification_email column
if (!$connection->tableColumnExists($tableName, 'notification_email')) {
    $connection->addColumn($tableName, 'notification_email', [
        'type' => Maho\Db\Ddl\Table::TYPE_VARCHAR,
        'length' => 255,
        'nullable' => true,
        'comment' => 'Custom notification email(s), comma-separated',
    ]);
}

// Add notification_sent column
if (!$connection->tableColumnExists($tableName, 'notification_sent')) {
    $connection->addColumn($tableName, 'notification_sent', [
        'type' => Maho\Db\Ddl\Table::TYPE_SMALLINT,
        'nullable' => false,
        'default' => 0,
        'comment' => 'Notification sent flag for once_until_success mode',
    ]);
}

$installer->endSetup();
