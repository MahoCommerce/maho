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

/**
 * Install eav entity types to the eav/entity_type table
 */
$installer->addEntityType('order', [
    'entity_model'          => 'sales/order',
    'table'                 => 'sales/order',
    'increment_model'       => 'eav/entity_increment_numeric',
    'increment_per_store'   => true,
]);

$installer->addEntityType('invoice', [
    'entity_model'          => 'sales/order_invoice',
    'table'                 => 'sales/invoice',
    'increment_model'       => 'eav/entity_increment_numeric',
    'increment_per_store'   => true,
]);

$installer->addEntityType('creditmemo', [
    'entity_model'          => 'sales/order_creditmemo',
    'table'                 => 'sales/creditmemo',
    'increment_model'       => 'eav/entity_increment_numeric',
    'increment_per_store'   => true,
]);

$installer->addEntityType('shipment', [
    'entity_model'          => 'sales/order_shipment',
    'table'                 => 'sales/shipment',
    'increment_model'       => 'eav/entity_increment_numeric',
    'increment_per_store'   => true,
]);

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
