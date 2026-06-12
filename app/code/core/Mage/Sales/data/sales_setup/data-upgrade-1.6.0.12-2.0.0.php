<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer = $this;

// Postgres cannot store the legacy zero-date sentinel, so there is nothing to clean.
if ($installer->getConnection() instanceof \Maho\Db\Adapter\Pdo\Pgsql) {
    return;
}

$installer->startSetup();

// Clear legacy zero-date sentinels left by the old Magento1/OpenMage schema; these columns are now nullable.
$columns = [
    'sales_flat_order' => ['created_at', 'updated_at', 'customer_dob'],
    'sales_flat_order_grid' => ['created_at', 'updated_at'],
    'sales_flat_order_status_history' => ['created_at'],
    'sales_flat_shipment' => ['created_at', 'updated_at'],
    'sales_flat_shipment_grid' => ['created_at', 'order_created_at'],
    'sales_flat_shipment_comment' => ['created_at'],
    'sales_flat_shipment_track' => ['created_at', 'updated_at'],
    'sales_flat_invoice' => ['created_at', 'updated_at'],
    'sales_flat_invoice_grid' => ['created_at', 'order_created_at'],
    'sales_flat_invoice_comment' => ['created_at'],
    'sales_flat_creditmemo' => ['created_at', 'updated_at'],
    'sales_flat_creditmemo_grid' => ['created_at', 'order_created_at'],
    'sales_flat_creditmemo_comment' => ['created_at'],
    'sales_flat_quote' => ['converted_at', 'customer_dob'],
    'sales_payment_transaction' => ['created_at'],
    'sales_billing_agreement' => ['updated_at'],
    'sales_recurring_profile' => ['updated_at'],
    'sales_bestsellers_aggregated_daily' => ['period'],
    'sales_bestsellers_aggregated_monthly' => ['period'],
    'sales_bestsellers_aggregated_yearly' => ['period'],
    'sales_invoiced_aggregated' => ['period'],
    'sales_invoiced_aggregated_order' => ['period'],
    'sales_order_aggregated_created' => ['period'],
    'sales_order_aggregated_updated' => ['period'],
    'sales_refunded_aggregated' => ['period'],
    'sales_refunded_aggregated_order' => ['period'],
    'sales_shipping_aggregated' => ['period'],
    'sales_shipping_aggregated_order' => ['period'],
];

$connection = $installer->getConnection();
foreach ($columns as $table => $tableColumns) {
    $table = $installer->getTable($table);
    if (!$connection->isTableExists($table)) {
        continue;
    }
    foreach ($tableColumns as $column) {
        $connection->update($table, [$column => null], $connection->quoteIdentifier($column) . " LIKE '0000-00-00%'");
    }
}

$installer->endSetup();
