<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

$installer->startSetup();

// Add meta_robots attribute for categories
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

// Add meta_robots attribute for products
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

// Add meta_robots attribute to all existing product attribute sets
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

$installer->endSetup();
