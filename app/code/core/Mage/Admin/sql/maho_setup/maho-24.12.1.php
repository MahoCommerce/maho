<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$tableName = $installer->getTable('sales/order_status');

$connection->addColumn(
    $tableName,
    'color',
    [
        'TYPE' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'LENGTH' => 20,
        'NULLABLE' => true,
        'COMMENT' => 'Status Color',
    ],
);

$installer->endSetup();
