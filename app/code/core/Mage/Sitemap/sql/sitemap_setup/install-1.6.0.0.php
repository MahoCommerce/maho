<?php

/**
 * Maho
 *
 * @package    Mage_Sitemap
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'sitemap'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('sitemap/sitemap'))
    ->addColumn('sitemap_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Sitemap Id')
    ->addColumn('sitemap_type', Maho\Db\Ddl\Table::TYPE_TEXT, 32, [
    ], 'Sitemap Type')
    ->addColumn('sitemap_filename', Maho\Db\Ddl\Table::TYPE_TEXT, 32, [
    ], 'Sitemap Filename')
    ->addColumn('sitemap_path', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Sitemap Path')
    ->addColumn('sitemap_time', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable'  => true,
    ], 'Sitemap Time')
    ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Store id')
    ->addIndex(
        $installer->getIdxName('sitemap/sitemap', ['store_id']),
        ['store_id'],
    )
    ->addForeignKey(
        $installer->getFkName('sitemap/sitemap', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Google Sitemap');

$installer->getConnection()->createTable($table);

$installer->endSetup();
