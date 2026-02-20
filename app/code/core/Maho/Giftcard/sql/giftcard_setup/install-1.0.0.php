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

/** @var Mage_Core_Model_Resource_Setup $this */
$this->startSetup();

$connection = $this->getConnection();

// ============================================================================
// Create gift card table
// ============================================================================
$table = $connection->newTable($this->getTable('giftcard/giftcard'))
    ->addColumn('giftcard_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Gift Card ID')
    ->addColumn('code', Maho\Db\Ddl\Table::TYPE_VARCHAR, 64, [
        'nullable' => false,
    ], 'Gift Card Code')
    ->addColumn('status', Maho\Db\Ddl\Table::TYPE_VARCHAR, 32, [
        'nullable' => false,
        'default'  => 'active',
    ], 'Status')
    ->addColumn('website_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Website ID')
    ->addColumn('balance', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12, 4], [
        'nullable' => false,
        'default'  => '0.0000',
    ], 'Current Balance')
    ->addColumn('initial_balance', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12, 4], [
        'nullable' => false,
        'default'  => '0.0000',
    ], 'Initial Balance')
    ->addColumn('recipient_name', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Recipient Name')
    ->addColumn('recipient_email', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Recipient Email')
    ->addColumn('sender_name', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Sender Name')
    ->addColumn('sender_email', Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Sender Email')
    ->addColumn('message', Maho\Db\Ddl\Table::TYPE_TEXT, null, [
        'nullable' => true,
    ], 'Gift Message')
    ->addColumn('purchase_order_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Purchase Order ID')
    ->addColumn('purchase_order_item_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Purchase Order Item ID')
    ->addColumn('expires_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable' => true,
    ], 'Expiration Date')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Created At')
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Updated At')
    ->addColumn('email_scheduled_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable' => true,
    ], 'Email Scheduled Send Time')
    ->addColumn('email_sent_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable' => true,
    ], 'Email Sent At')
    ->addIndex(
        $this->getIdxName('giftcard/giftcard', ['code'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['code'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $this->getIdxName('giftcard/giftcard', ['website_id']),
        ['website_id'],
    )
    ->addIndex(
        $this->getIdxName('giftcard/giftcard', ['status']),
        ['status'],
    )
    ->addIndex(
        $this->getIdxName('giftcard/giftcard', ['status', 'expires_at']),
        ['status', 'expires_at'],
    )
    ->addIndex(
        $this->getIdxName('giftcard/giftcard', ['purchase_order_id']),
        ['purchase_order_id'],
    )
    ->addIndex(
        $this->getIdxName('giftcard/giftcard', ['email_scheduled_at', 'email_sent_at']),
        ['email_scheduled_at', 'email_sent_at'],
    )
    ->addForeignKey(
        $this->getFkName('giftcard/giftcard', 'website_id', 'core/website', 'website_id'),
        'website_id',
        $this->getTable('core/website'),
        'website_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $this->getFkName('giftcard/giftcard', 'purchase_order_id', 'sales/order', 'entity_id'),
        'purchase_order_id',
        $this->getTable('sales/order'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_SET_NULL,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Gift Card Table');

$connection->createTable($table);

// ============================================================================
// Create gift card history table
// ============================================================================
$table = $connection->newTable($this->getTable('giftcard/history'))
    ->addColumn('history_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'History ID')
    ->addColumn('giftcard_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Gift Card ID')
    ->addColumn('action', Maho\Db\Ddl\Table::TYPE_VARCHAR, 32, [
        'nullable' => false,
    ], 'Action')
    ->addColumn('base_amount', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12, 4], [
        'nullable' => false,
        'default'  => '0.0000',
    ], 'Amount (Base Currency)')
    ->addColumn('balance_before', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12, 4], [
        'nullable' => false,
        'default'  => '0.0000',
    ], 'Balance Before (Base Currency)')
    ->addColumn('balance_after', Maho\Db\Ddl\Table::TYPE_DECIMAL, [12, 4], [
        'nullable' => false,
        'default'  => '0.0000',
    ], 'Balance After (Base Currency)')
    ->addColumn('order_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Order ID (for usage)')
    ->addColumn('admin_user_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Admin User ID')
    ->addColumn('comment', Maho\Db\Ddl\Table::TYPE_TEXT, null, [
        'nullable' => true,
    ], 'Comment')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Created At')
    ->addIndex(
        $this->getIdxName('giftcard/history', ['giftcard_id']),
        ['giftcard_id'],
    )
    ->addIndex(
        $this->getIdxName('giftcard/history', ['giftcard_id', 'created_at']),
        ['giftcard_id', 'created_at'],
    )
    ->addIndex(
        $this->getIdxName('giftcard/history', ['order_id']),
        ['order_id'],
    )
    ->addForeignKey(
        $this->getFkName('giftcard/history', 'giftcard_id', 'giftcard/giftcard', 'giftcard_id'),
        'giftcard_id',
        $this->getTable('giftcard/giftcard'),
        'giftcard_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $this->getFkName('giftcard/history', 'order_id', 'sales/order', 'entity_id'),
        'order_id',
        $this->getTable('sales/order'),
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_SET_NULL,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Gift Card History Table');

$connection->createTable($table);

// ============================================================================
// Add gift card columns to sales tables
// ============================================================================

// Quote table
$connection->addColumn($this->getTable('sales/quote'), 'giftcard_codes', [
    'type'     => Maho\Db\Ddl\Table::TYPE_TEXT,
    'nullable' => true,
    'comment'  => 'Applied Gift Card Codes (JSON)',
]);

$connection->addColumn($this->getTable('sales/quote'), 'giftcard_amount', [
    'type'     => Maho\Db\Ddl\Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Gift Card Discount Amount',
]);

$connection->addColumn($this->getTable('sales/quote'), 'base_giftcard_amount', [
    'type'     => Maho\Db\Ddl\Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Base Gift Card Discount Amount',
]);

// Order table
$connection->addColumn($this->getTable('sales/order'), 'giftcard_codes', [
    'type'     => Maho\Db\Ddl\Table::TYPE_TEXT,
    'nullable' => true,
    'comment'  => 'Applied Gift Card Codes (JSON)',
]);

$connection->addColumn($this->getTable('sales/order'), 'giftcard_amount', [
    'type'     => Maho\Db\Ddl\Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Gift Card Discount Amount',
]);

$connection->addColumn($this->getTable('sales/order'), 'base_giftcard_amount', [
    'type'     => Maho\Db\Ddl\Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Base Gift Card Discount Amount',
]);

// Invoice table
$connection->addColumn($this->getTable('sales/invoice'), 'giftcard_amount', [
    'type'     => Maho\Db\Ddl\Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Gift Card Amount',
]);

$connection->addColumn($this->getTable('sales/invoice'), 'base_giftcard_amount', [
    'type'     => Maho\Db\Ddl\Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Base Gift Card Amount',
]);

// Credit memo table
$connection->addColumn($this->getTable('sales/creditmemo'), 'giftcard_amount', [
    'type'     => Maho\Db\Ddl\Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Gift Card Amount',
]);

$connection->addColumn($this->getTable('sales/creditmemo'), 'base_giftcard_amount', [
    'type'     => Maho\Db\Ddl\Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Base Gift Card Amount',
]);

// ============================================================================
// Add gift card product EAV attributes
// ============================================================================
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
