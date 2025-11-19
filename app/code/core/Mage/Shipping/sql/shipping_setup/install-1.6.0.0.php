<?php

/**
 * Maho
 *
 * @package    Mage_Shipping
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'shipping/tablerate'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('shipping/tablerate'))
    ->addColumn('pk', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Primary key')
    ->addColumn('website_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Website Id')
    ->addColumn('dest_country_id', Maho\Db\Ddl\Table::TYPE_TEXT, 4, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Destination coutry ISO/2 or ISO/3 code')
    ->addColumn('dest_region_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Destination Region Id')
    ->addColumn('dest_zip', Maho\Db\Ddl\Table::TYPE_TEXT, 10, [
        'nullable'  => false,
        'default'   => '*',
    ], 'Destination Post Code (Zip)')
    ->addColumn('condition_name', Maho\Db\Ddl\Table::TYPE_TEXT, 20, [
        'nullable'  => false,
    ], 'Rate Condition name')
    ->addColumn('condition_value', Maho\Db\Ddl\Table::TYPE_DECIMAL, '12,4', [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Rate condition value')
    ->addColumn('price', Maho\Db\Ddl\Table::TYPE_DECIMAL, '12,4', [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Price')
    ->addColumn('cost', Maho\Db\Ddl\Table::TYPE_DECIMAL, '12,4', [
        'nullable'  => false,
        'default'   => '0.0000',
    ], 'Cost')
    ->addIndex(
        $installer->getIdxName('shipping/tablerate', ['website_id', 'dest_country_id', 'dest_region_id', 'dest_zip', 'condition_name', 'condition_value'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['website_id', 'dest_country_id', 'dest_region_id', 'dest_zip', 'condition_name', 'condition_value'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->setComment('Shipping Tablerate');
$installer->getConnection()->createTable($table);

$installer->endSetup();
