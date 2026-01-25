<?php

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$logTable = $installer->getTable('feedmanager/log');

/**
 * Add upload tracking columns to log table
 */
$connection->addColumn($logTable, 'upload_status', [
    'type' => Maho\Db\Ddl\Table::TYPE_VARCHAR,
    'length' => 20,
    'nullable' => true,
    'comment' => 'Upload Status (pending, success, failed, skipped)',
]);

$connection->addColumn($logTable, 'uploaded_at', [
    'type' => Maho\Db\Ddl\Table::TYPE_DATETIME,
    'nullable' => true,
    'comment' => 'Upload Completed At',
]);

$connection->addColumn($logTable, 'upload_message', [
    'type' => Maho\Db\Ddl\Table::TYPE_VARCHAR,
    'length' => 500,
    'nullable' => true,
    'comment' => 'Upload Result Message',
]);

$connection->addColumn($logTable, 'destination_id', [
    'type' => Maho\Db\Ddl\Table::TYPE_INTEGER,
    'unsigned' => true,
    'nullable' => true,
    'comment' => 'Destination ID (if uploaded)',
]);

$installer->endSetup();
