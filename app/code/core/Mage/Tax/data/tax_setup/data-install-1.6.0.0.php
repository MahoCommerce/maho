<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

/** @var Mage_Tax_Model_Resource_Setup $this */
$installer = $this;

/**
 * install tax classes
 */
$data = [
    [
        'class_id'     => 2,
        'class_name'   => 'Taxable Goods',
        'class_type'   => Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT,
    ],
    [
        'class_id'     => 3,
        'class_name'   => 'Retail Customer',
        'class_type'   => Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER,
    ],
    [
        'class_id'     => 4,
        'class_name'   => 'Shipping',
        'class_type'   => Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT,
    ],
];
foreach ($data as $row) {
    $installer->getConnection()->insertForce($installer->getTable('tax/tax_class'), $row);
}

/**
 * install tax calculation rule
 */
$data = [
    'tax_calculation_rule_id'   => 1,
    'code'                      => 'Retail Customer-Taxable Goods-Rate 1',
    'priority'                  => 1,
    'position'                  => 1,
];
$installer->getConnection()->insertForce($installer->getTable('tax/tax_calculation_rule'), $data);

/**
 * install tax calculation rates
 */
$data = [
    [
        'tax_calculation_rate_id'   => 1,
        'tax_country_id'            => 'US',
        'tax_region_id'             => 12,
        'tax_postcode'              => '*',
        'code'                      => 'US-CA-*-Rate 1',
        'rate'                      => '8.2500',
    ],
    [
        'tax_calculation_rate_id'   => 2,
        'tax_country_id'            => 'US',
        'tax_region_id'             => 43,
        'tax_postcode'              => '*',
        'code'                      => 'US-NY-*-Rate 1',
        'rate'                      => '8.3750',
    ],
];
foreach ($data as $row) {
    $installer->getConnection()->insertForce($installer->getTable('tax/tax_calculation_rate'), $row);
}

/**
 * install tax calculation
 */
$data = [
    [
        'tax_calculation_rate_id'   => 1,
        'tax_calculation_rule_id'   => 1,
        'customer_tax_class_id'     => 3,
        'product_tax_class_id'      => 2,
    ],
    [
        'tax_calculation_rate_id'   => 2,
        'tax_calculation_rule_id'   => 1,
        'customer_tax_class_id'     => 3,
        'product_tax_class_id'      => 2,
    ],
];
$installer->getConnection()->insertMultiple($installer->getTable('tax/tax_calculation'), $data);

/**
 * Register tax_class_id attribute on the catalog product entity.
 * Moved here from the legacy schema install script; the table structure
 * lives in sql/schema.php now and attribute registration is data work.
 */
$catalogInstaller = Mage::getResourceModel('catalog/setup', 'catalog_setup');
$catalogInstaller->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'tax_class_id', [
    'group'                      => 'Prices',
    'type'                       => 'int',
    'backend'                    => '',
    'frontend'                   => '',
    'label'                      => 'Tax Class',
    'input'                      => 'select',
    'class'                      => '',
    'source'                     => 'tax/class_source_product',
    'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
    'visible'                    => true,
    'required'                   => true,
    'user_defined'               => false,
    'default'                    => '',
    'searchable'                 => true,
    'filterable'                 => false,
    'comparable'                 => false,
    'visible_on_front'           => false,
    'visible_in_advanced_search' => true,
    'used_in_product_listing'    => true,
    'unique'                     => false,
    'apply_to'                   => 'simple,configurable,virtual,downloadable,bundle',
]);
