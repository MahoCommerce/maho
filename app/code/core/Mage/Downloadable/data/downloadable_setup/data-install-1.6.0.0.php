<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

$installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'links_purchased_separately', [
    'type'                    => 'int',
    'backend'                 => '',
    'frontend'                => '',
    'label'                   => 'Links can be purchased separately',
    'input'                   => '',
    'class'                   => '',
    'source'                  => '',
    'global'                  => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'                 => false,
    'required'                => true,
    'user_defined'            => false,
    'default'                 => '',
    'searchable'              => false,
    'filterable'              => false,
    'comparable'              => false,
    'visible_on_front'        => false,
    'unique'                  => false,
    'apply_to'                => 'downloadable',
    'is_configurable'         => false,
    'used_in_product_listing' => true,
]);

$installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'samples_title', [
    'type'              => 'varchar',
    'backend'           => '',
    'frontend'          => '',
    'label'             => 'Samples title',
    'input'             => '',
    'class'             => '',
    'source'            => '',
    'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'           => false,
    'required'          => true,
    'user_defined'      => false,
    'default'           => '',
    'searchable'        => false,
    'filterable'        => false,
    'comparable'        => false,
    'visible_on_front'  => false,
    'unique'            => false,
    'apply_to'          => 'downloadable',
    'is_configurable'   => false,
]);

$installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'links_title', [
    'type'              => 'varchar',
    'backend'           => '',
    'frontend'          => '',
    'label'             => 'Links title',
    'input'             => '',
    'class'             => '',
    'source'            => '',
    'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'           => false,
    'required'          => true,
    'user_defined'      => false,
    'default'           => '',
    'searchable'        => false,
    'filterable'        => false,
    'comparable'        => false,
    'visible_on_front'  => false,
    'unique'            => false,
    'apply_to'          => 'downloadable',
    'is_configurable'   => false,
]);

$installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'links_exist', [
    'type'                      => 'int',
    'backend'                   => '',
    'frontend'                  => '',
    'label'                     => '',
    'input'                     => '',
    'class'                     => '',
    'source'                    => '',
    'global'                    => true,
    'visible'                   => false,
    'required'                  => false,
    'user_defined'              => false,
    'default'                   => '0',
    'searchable'                => false,
    'filterable'                => false,
    'comparable'                => false,
    'visible_on_front'          => false,
    'unique'                    => false,
    'apply_to'                  => 'downloadable',
    'is_configurable'           => false,
    'used_in_product_listing'   => 1,
]);

$fieldList = [
    'price',
    'special_price',
    'special_from_date',
    'special_to_date',
    'minimal_price',
    'cost',
    'tier_price',
];

// make these attributes applicable to downloadable products
foreach ($fieldList as $field) {
    $applyTo = explode(',', $installer->getAttribute(Mage_Catalog_Model_Product::ENTITY, $field, 'apply_to'));
    if (!in_array('downloadable', $applyTo)) {
        $applyTo[] = 'downloadable';
        $installer->updateAttribute(Mage_Catalog_Model_Product::ENTITY, $field, 'apply_to', implode(',', $applyTo));
    }
}
