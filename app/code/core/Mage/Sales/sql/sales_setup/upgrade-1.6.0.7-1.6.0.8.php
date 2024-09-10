<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2022 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Sales_Model_Entity_Setup $installer */
$installer = $this;

$invoiceTable = $installer->getTable('sales/invoice');
$installer->getConnection()
    ->addColumn($invoiceTable, 'discount_description', [
            'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
            'length'    => 255,
            'comment'   => 'Discount Description'
    ]);

$creditmemoTable = $installer->getTable('sales/creditmemo');
$installer->getConnection()
    ->addColumn($creditmemoTable, 'discount_description', [
            'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
            'length'    => 255,
            'comment'   => 'Discount Description'
    ]);
