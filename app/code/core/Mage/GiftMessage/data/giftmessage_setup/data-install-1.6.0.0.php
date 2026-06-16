<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_GiftMessage
 */

/** @var Mage_GiftMessage_Model_Resource_Setup $this */
$installer = $this;

/**
 * Register gift_message_id attributes on sales entities.
 * Moved here from the legacy schema install script; the table structure
 * lives in sql/schema.php now and attribute registration is data work.
 */
$entities = [
    'quote',
    'quote_address',
    'quote_item',
    'quote_address_item',
    'order',
    'order_item',
];
$options = [
    'type'     => Maho\Db\Ddl\Table::TYPE_INTEGER,
    'visible'  => false,
    'required' => false,
];
foreach ($entities as $entity) {
    $installer->addAttribute($entity, 'gift_message_id', $options);
}

/**
 * Register gift_message_available attributes for order_item and the catalog product entity.
 */
$installer->addAttribute('order_item', 'gift_message_available', $options);
Mage::getResourceModel('catalog/setup', 'catalog_setup')->addAttribute(
    Mage_Catalog_Model_Product::ENTITY,
    'gift_message_available',
    [
        'group'         => 'Gift Options',
        'backend'       => 'catalog/product_attribute_backend_boolean',
        'frontend'      => '',
        'label'         => 'Allow Gift Message',
        'input'         => 'select',
        'class'         => '',
        'source'        => 'eav/entity_attribute_source_boolean',
        'global'        => true,
        'visible'       => true,
        'required'      => false,
        'user_defined'  => false,
        'default'       => '',
        'apply_to'      => '',
        'input_renderer'   => 'giftmessage/adminhtml_product_helper_form_config',
        'is_configurable'  => 0,
        'visible_on_front' => false,
    ],
);
