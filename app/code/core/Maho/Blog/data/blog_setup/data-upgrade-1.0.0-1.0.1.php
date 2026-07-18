<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

/** @var Mage_Eav_Model_Entity_Setup $this */
$installer = $this;
$installer->startSetup();

// Update entity type configuration
$installer->updateEntityType('blog_post', [
    'attribute_model'            => 'blog/resource_eav_attribute',
    'additional_attribute_table' => 'blog/eav_attribute',
]);

// Insert rows into blog_eav_attribute for existing attributes
$connection = $installer->getConnection();
$entityTypeId = $installer->getEntityTypeId('blog_post');

// Get all blog_post attributes
$select = $connection->select()
    ->from($installer->getTable('eav/attribute'), ['attribute_id'])
    ->where('entity_type_id = ?', $entityTypeId);

$attributeIds = $connection->fetchCol($select);

// Insert default values for each attribute
foreach ($attributeIds as $attributeId) {
    $connection->insert(
        $installer->getTable('blog/eav_attribute'),
        [
            'attribute_id' => $attributeId,
            'is_global'    => Maho_Blog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
            'position'     => 0,
        ],
    );
}

$installer->endSetup();
