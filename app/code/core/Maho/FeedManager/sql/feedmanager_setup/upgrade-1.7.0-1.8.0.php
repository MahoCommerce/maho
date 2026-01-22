<?php

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
$feedTable = $installer->getTable('feedmanager/feed');

// Add schedule column for cron-based feed generation
if (!$connection->tableColumnExists($feedTable, 'schedule')) {
    $connection->addColumn($feedTable, 'schedule', [
        'type' => 'varchar',
        'length' => 50,
        'nullable' => true,
        'default' => null,
        'comment' => 'Generation Schedule (hourly, daily, twice_daily, or comma-separated hours)',
        'after' => 'auto_upload',
    ]);
}

$installer->endSetup();
