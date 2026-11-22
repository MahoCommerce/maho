<?php

/**
 * Adds the category attributes new_product_placement and out_of_stock_last, and renames the attribute
 * group Dynamic Category to Dynamic Rules.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

$installer->addAttribute(Mage_Catalog_Model_Category::ENTITY, 'new_product_placement', [
    'type'          => 'varchar',
    'label'         => 'New Products Go To',
    'input'         => 'select',
    'source'        => 'catalog/category_attribute_source_placement',
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'       => true,
    'required'      => false,
    'user_defined'  => false,
    'default'       => Mage_Catalog_Model_Category::PLACEMENT_TOP,
    'sort_order'    => 60,
    'group'         => 'Display Settings',
]);

$installer->addAttribute(Mage_Catalog_Model_Category::ENTITY, 'out_of_stock_last', [
    'type'          => 'int',
    'label'         => 'Show Out of Stock Products Last',
    'input'         => 'select',
    'source'        => 'eav/entity_attribute_source_boolean',
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'       => true,
    'required'      => false,
    'user_defined'  => false,
    'default'       => '0',
    'sort_order'    => 70,
    'group'         => 'Display Settings',
]);

$connection = $installer->getConnection();
$categorySetIds = $connection->fetchCol(
    $connection->select()
        ->from($installer->getTable('eav/attribute_set'), ['attribute_set_id'])
        ->where('entity_type_id = ?', $installer->getEntityTypeId(Mage_Catalog_Model_Category::ENTITY)),
);
if ($categorySetIds) {
    $connection->update(
        $installer->getTable('eav/attribute_group'),
        ['attribute_group_name' => 'Dynamic Rules'],
        ['attribute_group_name = ?' => 'Dynamic Category', 'attribute_set_id IN (?)' => $categorySetIds],
    );
}
