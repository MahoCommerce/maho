<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer = $this;

/**
 * Install order statuses from config
 */
$data     = [];
$statuses = Mage::getConfig()->getNode('global/sales/order/statuses')->asArray();
foreach ($statuses as $code => $info) {
    $data[] = [
        'status' => $code,
        'label'  => $info['label'],
    ];
}
$installer->getConnection()->insertArray(
    $installer->getTable('sales/order_status'),
    ['status', 'label'],
    $data,
);

/**
 * Install order states from config
 */
$data   = [];
$states = Mage::getConfig()->getNode('global/sales/order/states')->asArray();

foreach ($states as $code => $info) {
    if (isset($info['statuses'])) {
        foreach ($info['statuses'] as $status => $statusInfo) {
            $data[] = [
                'status'     => $status,
                'state'      => $code,
                'is_default' => is_array($statusInfo) && isset($statusInfo['@']['default']) ? 1 : 0,
            ];
        }
    }
}
$installer->getConnection()->insertArray(
    $installer->getTable('sales/order_status_state'),
    ['status', 'state', 'is_default'],
    $data,
);
