<?php

/**
 * Backfill shipment_status for shipments created before the status was tracked.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer = $this;
$connection = $installer->getConnection();

foreach (['sales/shipment', 'sales/shipment_grid'] as $table) {
    $connection->update(
        $installer->getTable($table),
        ['shipment_status' => Mage_Sales_Model_Order_Shipment::STATUS_NEW],
        'shipment_status IS NULL',
    );
}
