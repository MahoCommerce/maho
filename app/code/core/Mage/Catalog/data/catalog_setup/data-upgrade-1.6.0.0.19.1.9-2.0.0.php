<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

// Dynamic category flag (catalog_category_dynamic_rule table lives in sql/schema.php)
$installer->addAttribute('catalog_category', 'is_dynamic', [
    'type'                       => 'int',
    'group'                      => 'Dynamic Category',
    'label'                      => 'Is Dynamic Category',
    'input'                      => 'select',
    'source'                     => 'eav/entity_attribute_source_boolean',
    'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'                    => true,
    'required'                   => false,
    'user_defined'               => true,
    'default'                    => '0',
]);

// meta_robots for categories
$installer->addAttribute(Mage_Catalog_Model_Category::ENTITY, 'meta_robots', [
    'type'              => 'varchar',
    'backend'           => '',
    'frontend'          => '',
    'label'             => 'Meta Robots',
    'input'             => 'select',
    'class'             => '',
    'source'            => 'page/source_robots',
    'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'           => true,
    'required'          => false,
    'user_defined'      => false,
    'default'           => '',
    'searchable'        => false,
    'filterable'        => false,
    'comparable'        => false,
    'visible_on_front'  => false,
    'unique'            => false,
    'group'             => 'General Information',
]);

// meta_robots for products
$installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'meta_robots', [
    'type'              => 'varchar',
    'backend'           => '',
    'frontend'          => '',
    'label'             => 'Meta Robots',
    'input'             => 'select',
    'class'             => '',
    'source'            => 'page/source_robots',
    'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'           => true,
    'required'          => false,
    'user_defined'      => false,
    'default'           => '',
    'searchable'        => false,
    'filterable'        => false,
    'comparable'        => false,
    'visible_on_front'  => false,
    'unique'            => false,
    'apply_to'          => '',
    'is_configurable'   => false,
    'group'             => 'Meta Information',
]);

// Add meta_robots to every existing product attribute set
$attributeSetCollection = Mage::getResourceModel('eav/entity_attribute_set_collection')
    ->setEntityTypeFilter($installer->getEntityTypeId('catalog_product'));

foreach ($attributeSetCollection as $attributeSet) {
    $attributeGroupId = $installer->getAttributeGroupId(
        $installer->getEntityTypeId('catalog_product'),
        (int) $attributeSet->getId(),
        'Meta Information',
    );

    if ($attributeGroupId) {
        $installer->addAttributeToSet(
            'catalog_product',
            $attributeSet->getId(),
            $attributeGroupId,
            'meta_robots',
        );
    }
}

// GTIN (Global Trade Item Number - encompasses UPC, EAN, ISBN, ITF-14)
$installer->addAttribute('catalog_product', 'gtin', [
    'type'                       => 'varchar',
    'label'                      => 'GTIN/UPC/EAN/Barcode',
    'input'                      => 'text',
    'required'                   => false,
    'sort_order'                 => 4,
    'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'searchable'                 => true,
    'visible_in_advanced_search' => true,
    'comparable'                 => false,
    'used_in_product_listing'    => false,
    'unique'                     => false,
    'apply_to'                   => '',
    'group'                      => 'General',
]);

// MPN (Manufacturer Part Number)
$installer->addAttribute('catalog_product', 'mpn', [
    'type'                       => 'varchar',
    'label'                      => 'MPN (Manufacturer Part Number)',
    'input'                      => 'text',
    'required'                   => false,
    'sort_order'                 => 4,
    'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'searchable'                 => true,
    'visible_in_advanced_search' => true,
    'comparable'                 => false,
    'used_in_product_listing'    => false,
    'unique'                     => false,
    'apply_to'                   => '',
    'group'                      => 'General',
]);

// Clear legacy zero-date sentinels left by the old Magento1/OpenMage schema; these columns are
// now nullable. MySQL/MariaDB only: Postgres cannot store the sentinel, so there is nothing to clean.
if (!($connection instanceof \Maho\Db\Adapter\Pdo\Pgsql)) {
    $columns = [
        'catalog_product_entity' => ['created_at', 'updated_at'],
        'catalog_category_entity' => ['created_at', 'updated_at'],
        'catalog_product_entity_datetime' => ['value'],
        'catalog_category_entity_datetime' => ['value'],
    ];
    foreach ($columns as $table => $tableColumns) {
        $table = $installer->getTable($table);
        if (!$connection->isTableExists($table)) {
            continue;
        }
        foreach ($tableColumns as $column) {
            $connection->update($table, [$column => null], $connection->quoteIdentifier($column) . " LIKE '0000-00-00%'");
        }
    }
}

$installer->endSetup();
