<?php

/**
 * Maho
 *
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Checkout_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$table = $installer->getTable('checkout/agreement');
$column = 'position';

if (!$connection->tableColumnExists($table, $column)) {
    $connection->addColumn(
        $table,
        $column,
        [
            'type'      => Varien_Db_Ddl_Table::TYPE_SMALLINT,
            'length'    => 2,
            'nullable'  => false,
            'default'   => 0,
            'comment'   => 'Agreement Position',
        ],
    );
}

$installer->endSetup();
