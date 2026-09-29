<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

/** @var Mage_Customer_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// The admin address forms follow sort_order: the country comes first, so it can drive the other fields
$sortOrders = [
    'country_id' => 70,
    'region'     => 72,
    'region_id'  => 72,
    'postcode'   => 74,
    'city'       => 76,
    'street'     => 78,
];
foreach ($sortOrders as $attributeCode => $sortOrder) {
    $installer->updateAttribute('customer_address', $attributeCode, 'sort_order', $sortOrder);
}

$installer->endSetup();
