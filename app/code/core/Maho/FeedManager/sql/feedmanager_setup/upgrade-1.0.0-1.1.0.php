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
/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

/**
 * Create table 'feedmanager_destination'
 * Central configuration for upload destinations (SFTP, FTP, API endpoints)
 */
$table = $connection
    ->newTable($installer->getTable('feedmanager/destination'))
    ->addColumn('destination_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Destination ID')
    ->addColumn('name', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => false,
    ], 'Destination Name')
    ->addColumn('type', Maho\Db\Ddl\Table::TYPE_VARCHAR, 50, [
        'nullable' => false,
    ], 'Destination Type (sftp, ftp, google_api, facebook_api)')
    ->addColumn('config', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
        'nullable' => true,
    ], 'Configuration (JSON - credentials, paths, etc)')
    ->addColumn('is_enabled', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default'  => 1,
    ], 'Is Enabled')
    ->addColumn('last_upload_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable' => true,
    ], 'Last Successful Upload')
    ->addColumn('last_upload_status', Maho\Db\Ddl\Table::TYPE_VARCHAR, 20, [
        'nullable' => true,
    ], 'Last Upload Status')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT_UPDATE,
    ], 'Updated At')
    ->addIndex(
        $installer->getIdxName('feedmanager/destination', ['type']),
        ['type'],
    )
    ->addIndex(
        $installer->getIdxName('feedmanager/destination', ['is_enabled']),
        ['is_enabled'],
    )
    ->setComment('Feed Manager - Upload Destinations');

$connection->createTable($table);

/**
 * Add destination_id to feed table
 */
$feedTable = $installer->getTable('feedmanager/feed');

$connection->addColumn($feedTable, 'destination_id', [
    'type'     => Maho\Db\Ddl\Table::TYPE_INTEGER,
    'unsigned' => true,
    'nullable' => true,
    'comment'  => 'Upload Destination ID',
    'after'    => 'configurable_mode',
]);

$connection->addColumn($feedTable, 'auto_upload', [
    'type'     => Maho\Db\Ddl\Table::TYPE_SMALLINT,
    'unsigned' => true,
    'nullable' => false,
    'default'  => 0,
    'comment'  => 'Auto Upload After Generation',
    'after'    => 'destination_id',
]);

$connection->addForeignKey(
    $installer->getFkName('feedmanager/feed', 'destination_id', 'feedmanager/destination', 'destination_id'),
    $feedTable,
    'destination_id',
    $installer->getTable('feedmanager/destination'),
    'destination_id',
    Maho\Db\Ddl\Table::ACTION_SET_NULL,
);

$installer->endSetup();
