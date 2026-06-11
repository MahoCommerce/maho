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

// Add GTIN attribute (Global Trade Item Number - encompasses UPC, EAN, ISBN, ITF-14)
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

// Add MPN attribute (Manufacturer Part Number)
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

$installer->endSetup();
