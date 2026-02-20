<?php

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$aggregationTables = [
    Mage_Reports_Model_Resource_Report_Product_Viewed::AGGREGATION_DAILY,
    Mage_Reports_Model_Resource_Report_Product_Viewed::AGGREGATION_MONTHLY,
    Mage_Reports_Model_Resource_Report_Product_Viewed::AGGREGATION_YEARLY,
];
$aggregationTableComments = [
    'Most Viewed Products Aggregated Daily',
    'Most Viewed Products Aggregated Monthly',
    'Most Viewed Products Aggregated Yearly',
];

for ($i = 0; $i < 3; ++$i) {
    $table = $installer->getConnection()
        ->newTable($installer->getTable($aggregationTables[$i]))
        ->addColumn('id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity'  => true,
            'unsigned'  => true,
            'nullable'  => false,
            'primary'   => true,
        ], 'Id')
        ->addColumn('period', Maho\Db\Ddl\Table::TYPE_DATE, null, [
        ], 'Period')
        ->addColumn('store_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
            'unsigned'  => true,
        ], 'Store Id')
        ->addColumn('product_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned'  => true,
        ], 'Product Id')
        ->addColumn('product_name', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
            'nullable'  => true,
        ], 'Product Name')
        ->addColumn('product_price', Maho\Db\Ddl\Table::TYPE_DECIMAL, '12,4', [
            'nullable'  => false,
            'default'   => '0.0000',
        ], 'Product Price')
        ->addColumn('views_num', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'nullable'  => false,
            'default'   => '0',
        ], 'Number of Views')
        ->addColumn('rating_pos', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
            'unsigned'  => true,
            'nullable'  => false,
            'default'   => '0',
        ], 'Rating Pos')
        ->addIndex(
            $installer->getIdxName(
                $aggregationTables[$i],
                ['period', 'store_id', 'product_id'],
                Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
            ),
            ['period', 'store_id', 'product_id'],
            ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
        )
        ->addIndex($installer->getIdxName($aggregationTables[$i], ['store_id']), ['store_id'])
        ->addIndex($installer->getIdxName($aggregationTables[$i], ['product_id']), ['product_id'])
        ->addForeignKey(
            $installer->getFkName($aggregationTables[$i], 'store_id', 'core/store', 'store_id'),
            'store_id',
            $installer->getTable('core/store'),
            'store_id',
            Maho\Db\Ddl\Table::ACTION_CASCADE,
            Maho\Db\Ddl\Table::ACTION_CASCADE,
        )
        ->addForeignKey(
            $installer->getFkName($aggregationTables[$i], 'product_id', 'catalog/product', 'entity_id'),
            'product_id',
            $installer->getTable('catalog/product'),
            'entity_id',
            Maho\Db\Ddl\Table::ACTION_CASCADE,
            Maho\Db\Ddl\Table::ACTION_CASCADE,
        )
        ->setComment($aggregationTableComments[$i]);
    $installer->getConnection()->createTable($table);
}

$installer->endSetup();
