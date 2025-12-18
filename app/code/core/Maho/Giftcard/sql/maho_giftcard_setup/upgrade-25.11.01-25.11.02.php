<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()
    ->newTable($installer->getTable('maho_giftcard/scheduled_email'))
    ->addColumn('scheduled_email_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Scheduled Email ID')
    ->addColumn('giftcard_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Gift Card ID')
    ->addColumn('recipient_email', Varien_Db_Ddl_Table::TYPE_TEXT, 255, [
        'nullable' => false,
    ], 'Recipient Email')
    ->addColumn('recipient_name', Varien_Db_Ddl_Table::TYPE_TEXT, 255, [
        'nullable' => true,
    ], 'Recipient Name')
    ->addColumn('scheduled_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Scheduled Send Time (UTC)')
    ->addColumn('status', Varien_Db_Ddl_Table::TYPE_TEXT, 20, [
        'nullable' => false,
        'default'  => 'pending',
    ], 'Status')
    ->addColumn('sent_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => true,
    ], 'Sent At')
    ->addColumn('error_message', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable' => true,
    ], 'Error Message')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Created At')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Updated At')
    ->addIndex(
        $installer->getIdxName('maho_giftcard/scheduled_email', ['giftcard_id']),
        ['giftcard_id'],
    )
    ->addIndex(
        $installer->getIdxName('maho_giftcard/scheduled_email', ['status']),
        ['status'],
    )
    ->addIndex(
        $installer->getIdxName('maho_giftcard/scheduled_email', ['scheduled_at']),
        ['scheduled_at'],
    )
    ->addForeignKey(
        $installer->getFkName('maho_giftcard/scheduled_email', 'giftcard_id', 'maho_giftcard/giftcard', 'giftcard_id'),
        'giftcard_id',
        $installer->getTable('maho_giftcard/giftcard'),
        'giftcard_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Gift Card Scheduled Emails');

$installer->getConnection()->createTable($table);

$installer->endSetup();
