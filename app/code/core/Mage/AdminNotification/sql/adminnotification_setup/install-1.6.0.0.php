<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_AdminNotification
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();
/**
 * Create table 'adminnotification/inbox'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('adminnotification/inbox'))
    ->addColumn('notification_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Notification id')
    ->addColumn('severity', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Problem type')
    ->addColumn('date_added', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
    ], 'Create date')
    ->addColumn('title', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ], 'Title')
    ->addColumn('description', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
    ], 'Description')
    ->addColumn('url', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Url')
    ->addColumn('is_read', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Flag if notification read')
    ->addColumn('is_remove', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Flag if notification might be removed')
    ->addIndex(
        $installer->getIdxName('adminnotification/inbox', ['severity']),
        ['severity'],
    )
    ->addIndex(
        $installer->getIdxName('adminnotification/inbox', ['is_read']),
        ['is_read'],
    )
    ->addIndex(
        $installer->getIdxName('adminnotification/inbox', ['is_remove']),
        ['is_remove'],
    )
    ->setComment('Adminnotification Inbox');
$installer->getConnection()->createTable($table);

$installer->endSetup();
