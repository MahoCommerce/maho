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
$feedTable = $installer->getTable('feedmanager/feed');

// Add gzip_compression column to feed table (per-feed setting)
if (!$connection->tableColumnExists($feedTable, 'gzip_compression')) {
    $connection->addColumn($feedTable, 'gzip_compression', [
        'type' => 'smallint',
        'unsigned' => true,
        'nullable' => false,
        'default' => 0,
        'comment' => 'Enable Gzip Compression',
    ]);
}

$installer->endSetup();
