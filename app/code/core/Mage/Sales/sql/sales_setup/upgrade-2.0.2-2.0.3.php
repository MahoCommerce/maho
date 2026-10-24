<?php

/**
 * Drops the tables and the column of the removed recurring profiles.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$connection->dropTable($installer->getTable('sales_recurring_profile_order'));
$connection->dropTable($installer->getTable('sales_recurring_profile'));

$orderItemTable = $installer->getTable('sales/order_item');
if ($connection->tableColumnExists($orderItemTable, 'is_nominal')) {
    $connection->dropColumn($orderItemTable, 'is_nominal');
}

$installer->endSetup();
