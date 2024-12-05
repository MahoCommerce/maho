<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Sales_Model_Entity_Setup $installer */
$installer = $this;
$installer->startSetup();

$bestsellersTables = [$installer->getTable('sales/bestsellers_aggregated_daily'),
    $installer->getTable('sales/bestsellers_aggregated_monthly'),
    $installer->getTable('sales/bestsellers_aggregated_yearly')];

foreach ($bestsellersTables as $table) {
    $installer->getConnection()->addColumn(
        $table,
        'product_type_id',
        [
            'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
            'length'   => 32,
            'default'  => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
            'nullable' => false,
            'after'    => 'product_id',
            'comment'  => 'Product Type Id'
        ]
    );
}

$installer->endSetup();
