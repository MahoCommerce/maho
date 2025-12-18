<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

// Add gift card columns to sales_flat_order if they don't exist
$orderTable = $installer->getTable('sales/order');

if (!$connection->tableColumnExists($orderTable, 'giftcard_codes')) {
    $connection->addColumn($orderTable, 'giftcard_codes', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => 65535,
        'nullable' => true,
        'comment' => 'Gift Card Codes (JSON)',
    ]);
}

if (!$connection->tableColumnExists($orderTable, 'base_giftcard_amount')) {
    $connection->addColumn($orderTable, 'base_giftcard_amount', [
        'type' => Maho\Db\Ddl\Table::TYPE_DECIMAL,
        'length' => '12,4',
        'nullable' => true,
        'default' => '0.0000',
        'comment' => 'Base Gift Card Amount',
    ]);
}

if (!$connection->tableColumnExists($orderTable, 'giftcard_amount')) {
    $connection->addColumn($orderTable, 'giftcard_amount', [
        'type' => Maho\Db\Ddl\Table::TYPE_DECIMAL,
        'length' => '12,4',
        'nullable' => true,
        'default' => '0.0000',
        'comment' => 'Gift Card Amount',
    ]);
}

// Add gift card columns to sales_flat_order_address if they don't exist
$addressTable = $installer->getTable('sales/order_address');

if (!$connection->tableColumnExists($addressTable, 'base_giftcard_amount')) {
    $connection->addColumn($addressTable, 'base_giftcard_amount', [
        'type' => Maho\Db\Ddl\Table::TYPE_DECIMAL,
        'length' => '12,4',
        'nullable' => true,
        'default' => '0.0000',
        'comment' => 'Base Gift Card Amount',
    ]);
}

if (!$connection->tableColumnExists($addressTable, 'giftcard_amount')) {
    $connection->addColumn($addressTable, 'giftcard_amount', [
        'type' => Maho\Db\Ddl\Table::TYPE_DECIMAL,
        'length' => '12,4',
        'nullable' => true,
        'default' => '0.0000',
        'comment' => 'Gift Card Amount',
    ]);
}

if (!$connection->tableColumnExists($addressTable, 'giftcard_codes')) {
    $connection->addColumn($addressTable, 'giftcard_codes', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'length' => 65535,
        'nullable' => true,
        'comment' => 'Gift Card Codes (JSON)',
    ]);
}

// Add gift card columns to sales_flat_invoice if they don't exist
$invoiceTable = $installer->getTable('sales/invoice');

if (!$connection->tableColumnExists($invoiceTable, 'base_giftcard_amount')) {
    $connection->addColumn($invoiceTable, 'base_giftcard_amount', [
        'type' => Maho\Db\Ddl\Table::TYPE_DECIMAL,
        'length' => '12,4',
        'nullable' => true,
        'default' => '0.0000',
        'comment' => 'Base Gift Card Amount',
    ]);
}

if (!$connection->tableColumnExists($invoiceTable, 'giftcard_amount')) {
    $connection->addColumn($invoiceTable, 'giftcard_amount', [
        'type' => Maho\Db\Ddl\Table::TYPE_DECIMAL,
        'length' => '12,4',
        'nullable' => true,
        'default' => '0.0000',
        'comment' => 'Gift Card Amount',
    ]);
}

// Add gift card columns to sales_flat_creditmemo if they don't exist
$creditmemoTable = $installer->getTable('sales/creditmemo');

if (!$connection->tableColumnExists($creditmemoTable, 'base_giftcard_amount')) {
    $connection->addColumn($creditmemoTable, 'base_giftcard_amount', [
        'type' => Maho\Db\Ddl\Table::TYPE_DECIMAL,
        'length' => '12,4',
        'nullable' => true,
        'default' => '0.0000',
        'comment' => 'Base Gift Card Amount',
    ]);
}

if (!$connection->tableColumnExists($creditmemoTable, 'giftcard_amount')) {
    $connection->addColumn($creditmemoTable, 'giftcard_amount', [
        'type' => Maho\Db\Ddl\Table::TYPE_DECIMAL,
        'length' => '12,4',
        'nullable' => true,
        'default' => '0.0000',
        'comment' => 'Gift Card Amount',
    ]);
}

$installer->endSetup();