<?php

/**
 * Maho
 *
 * @package    Mage_Cron
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'cron/schedule'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('cron/schedule'))
    ->addColumn('schedule_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Schedule Id')
    ->addColumn('job_code', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Job Code')
    ->addColumn('status', Maho\Db\Ddl\Table::TYPE_TEXT, 7, [
        'nullable'  => false,
        'default'   => 'pending',
    ], 'Status')
    ->addColumn('messages', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
    ], 'Messages')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'Created At')
    ->addColumn('scheduled_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => true,
    ], 'Scheduled At')
    ->addColumn('executed_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => true,
    ], 'Executed At')
    ->addColumn('finished_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => true,
    ], 'Finished At')
    ->addIndex(
        $installer->getIdxName('cron/schedule', ['job_code']),
        ['job_code'],
    )
    ->addIndex(
        $installer->getIdxName('cron/schedule', ['scheduled_at', 'status']),
        ['scheduled_at', 'status'],
    )
    ->setComment('Cron Schedule');
$installer->getConnection()->createTable($table);

$installer->endSetup();
