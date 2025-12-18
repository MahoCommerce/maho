<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

// Use EAV setup for adding attributes
$eavSetup = new Mage_Catalog_Model_Resource_Setup('catalog_setup');

// Add gift card product attributes
$attributes = [
    'giftcard_type' => [
        'type' => 'varchar',
        'label' => 'Gift Card Type',
        'input' => 'select',
        'source' => '',
        'required' => false,
        'sort_order' => 10,
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible' => true,
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
        'input' => 'textarea',
        'required' => false,
        'sort_order' => 20,
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible' => true,
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
        'visible' => true,
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
        'visible' => true,
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
        'visible' => true,
        'searchable' => false,
        'filterable' => false,
        'comparable' => false,
        'visible_on_front' => false,
        'used_in_product_listing' => false,
        'unique' => false,
        'apply_to' => 'giftcard',
        'default' => '1',
    ],
    'giftcard_lifetime' => [
        'type' => 'int',
        'label' => 'Gift Card Lifetime (Days)',
        'input' => 'text',
        'required' => false,
        'sort_order' => 60,
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible' => true,
        'searchable' => false,
        'filterable' => false,
        'comparable' => false,
        'visible_on_front' => false,
        'used_in_product_listing' => false,
        'unique' => false,
        'apply_to' => 'giftcard',
        'default' => '365',
        'note' => 'Number of days gift card is valid. Use 0 for no expiration.',
    ],
    'giftcard_is_redeemable' => [
        'type' => 'int',
        'label' => 'Is Redeemable',
        'input' => 'boolean',
        'required' => false,
        'sort_order' => 70,
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible' => true,
        'searchable' => false,
        'filterable' => false,
        'comparable' => false,
        'visible_on_front' => false,
        'used_in_product_listing' => false,
        'unique' => false,
        'apply_to' => 'giftcard',
        'default' => '1',
    ],
];

foreach ($attributes as $code => $config) {
    $eavSetup->addAttribute('catalog_product', $code, $config);
}

$installer->endSetup();
