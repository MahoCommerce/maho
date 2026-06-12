<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()
    ->newTable($installer->getTable('mediacleaner/image'))
    ->addColumn('image_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Image ID')
    ->addColumn('type', Maho\Db\Ddl\Table::TYPE_VARCHAR, 32, [
        'nullable'  => false,
    ], 'Cleanup type: category|product|product_cache|wysiwyg')
    ->addColumn('path', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ], 'Path relative to type root')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addIndex(
        $installer->getIdxName('mediacleaner/image', ['type', 'path'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['type', 'path'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->setComment('Media Cleaner orphan files');

$installer->getConnection()->createTable($table);

$installer->endSetup();
