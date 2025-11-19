<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer = $this;

$invoiceTable = $installer->getTable('sales/invoice');
$installer->getConnection()
    ->addColumn($invoiceTable, 'discount_description', [
        'type'      => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length'    => 255,
        'comment'   => 'Discount Description',
    ]);

$creditmemoTable = $installer->getTable('sales/creditmemo');
$installer->getConnection()
    ->addColumn($creditmemoTable, 'discount_description', [
        'type'      => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length'    => 255,
        'comment'   => 'Discount Description',
    ]);
