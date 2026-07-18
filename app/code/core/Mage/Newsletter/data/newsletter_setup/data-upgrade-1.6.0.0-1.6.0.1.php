<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;

$subscriberTable = $installer->getTable('newsletter/subscriber');

$select = $installer->getConnection()->select()
    ->from(['main_table' => $subscriberTable])
    ->join(
        ['customer' => $installer->getTable('customer/entity')],
        'main_table.customer_id = customer.entity_id',
        ['website_id'],
    )
    ->where('customer.website_id = 0');

$installer->getConnection()->query(
    $installer->getConnection()->deleteFromSelect($select, 'main_table'),
);
