<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$this->startSetup();

$eavSetup = new Mage_Catalog_Model_Resource_Setup('catalog_setup');

$attributes = [
    'giftcard_type' => [
        'type' => 'varchar',
        'label' => 'Gift Card Type',
        'input' => 'select',
        'source' => '',
        'required' => false,
        'sort_order' => 10,
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible' => false, // Rendered via custom tab
        'searchable' => false,
        'filterable' => false,
        'comparable' => false,
        'visible_on_front' => false,
        'used_in_product_listing' => false,
        'unique' => false,
        'apply_to' => 'giftcard',
        'option' => [
            'values' => ['Fixed Amount(s)', 'Custom Amount (Customer Enters Amount)'],
        ],
    ],
    'giftcard_amounts' => [
        'type' => 'text',
        'label' => 'Gift Card Amounts',
        'input' => 'text',
        'required' => false,
        'sort_order' => 20,
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible' => false, // Rendered via custom tab
        'searchable' => false,
        'filterable' => false,
        'comparable' => false,
        'visible_on_front' => false,
        'used_in_product_listing' => false,
        'unique' => false,
        'apply_to' => 'giftcard',
        'note' => 'Comma-separated amounts (e.g., 25,50,100,250,500)',
    ],
    'giftcard_min_amount' => [
        'type' => 'decimal',
        'label' => 'Minimum Amount',
        'input' => 'text',
        'required' => false,
        'sort_order' => 30,
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible' => false, // Rendered via custom tab
        'searchable' => false,
        'filterable' => false,
        'comparable' => false,
        'visible_on_front' => false,
        'used_in_product_listing' => false,
        'unique' => false,
        'apply_to' => 'giftcard',
    ],
    'giftcard_max_amount' => [
        'type' => 'decimal',
        'label' => 'Maximum Amount',
        'input' => 'text',
        'required' => false,
        'sort_order' => 40,
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible' => false, // Rendered via custom tab
        'searchable' => false,
        'filterable' => false,
        'comparable' => false,
        'visible_on_front' => false,
        'used_in_product_listing' => false,
        'unique' => false,
        'apply_to' => 'giftcard',
    ],
    'giftcard_allow_message' => [
        'type' => 'int',
        'label' => 'Allow Message',
        'input' => 'boolean',
        'required' => false,
        'sort_order' => 50,
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible' => false, // Rendered via custom tab with "Use Default" option
        'searchable' => false,
        'filterable' => false,
        'comparable' => false,
        'visible_on_front' => false,
        'used_in_product_listing' => false,
        'unique' => false,
        'apply_to' => 'giftcard',
        // No default - NULL means use system config
    ],
    'giftcard_lifetime' => [
        'type' => 'int',
        'label' => 'Gift Card Lifetime (Days)',
        'input' => 'text',
        'required' => false,
        'sort_order' => 60,
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible' => false, // Rendered via custom tab with placeholder
        'searchable' => false,
        'filterable' => false,
        'comparable' => false,
        'visible_on_front' => false,
        'used_in_product_listing' => false,
        'unique' => false,
        'apply_to' => 'giftcard',
        // No default - NULL means use system config
    ],
];

foreach ($attributes as $code => $config) {
    $eavSetup->addAttribute('catalog_product', $code, $config);
}

$this->endSetup();
