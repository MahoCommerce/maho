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
$table = $connection->newTable($this->getTable('maho_giftcard/giftcard'))
    ->addColumn('giftcard_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Gift Card ID')
    ->addColumn('code', Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, [
        'nullable' => false,
    ], 'Gift Card Code')
    ->addColumn('status', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, [
        'nullable' => false,
        'default'  => 'active',
    ], 'Status')
    ->addColumn('balance', Varien_Db_Ddl_Table::TYPE_DECIMAL, [12, 4], [
        'nullable' => false,
        'default'  => '0.0000',
    ], 'Current Balance')
    ->addColumn('initial_balance', Varien_Db_Ddl_Table::TYPE_DECIMAL, [12, 4], [
        'nullable' => false,
        'default'  => '0.0000',
    ], 'Initial Balance')
    ->addColumn('currency_code', Varien_Db_Ddl_Table::TYPE_VARCHAR, 3, [
        'nullable' => false,
        'default'  => 'AUD',
    ], 'Currency Code')
    ->addColumn('recipient_name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Recipient Name')
    ->addColumn('recipient_email', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Recipient Email')
    ->addColumn('sender_name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Sender Name')
    ->addColumn('sender_email', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
        'nullable' => true,
    ], 'Sender Email')
    ->addColumn('message', Varien_Db_Ddl_Table::TYPE_TEXT, null, [
        'nullable' => true,
    ], 'Gift Message')
    ->addColumn('purchase_order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Purchase Order ID')
    ->addColumn('purchase_order_item_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Purchase Order Item ID')
    ->addColumn('expires_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => true,
    ], 'Expiration Date')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Created At')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Updated At')
    ->addIndex(
        $this->getIdxName('maho_giftcard/giftcard', ['code'], Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE),
        ['code'],
        ['type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $this->getIdxName('maho_giftcard/giftcard', ['status']),
        ['status'],
    )
    ->addIndex(
        $this->getIdxName('maho_giftcard/giftcard', ['purchase_order_id']),
        ['purchase_order_id'],
    )
    ->addForeignKey(
        $this->getFkName('maho_giftcard/giftcard', 'purchase_order_id', 'sales/order', 'entity_id'),
        'purchase_order_id',
        $this->getTable('sales/order'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_SET_NULL,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Gift Card Table');

$connection->createTable($table);

// ============================================================================
// Create gift card history table
// ============================================================================
$table = $connection->newTable($this->getTable('maho_giftcard/history'))
    ->addColumn('history_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'History ID')
    ->addColumn('giftcard_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Gift Card ID')
    ->addColumn('action', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, [
        'nullable' => false,
    ], 'Action')
    ->addColumn('amount', Varien_Db_Ddl_Table::TYPE_DECIMAL, [12, 4], [
        'nullable' => false,
        'default'  => '0.0000',
    ], 'Amount')
    ->addColumn('balance_before', Varien_Db_Ddl_Table::TYPE_DECIMAL, [12, 4], [
        'nullable' => false,
        'default'  => '0.0000',
    ], 'Balance Before')
    ->addColumn('balance_after', Varien_Db_Ddl_Table::TYPE_DECIMAL, [12, 4], [
        'nullable' => false,
        'default'  => '0.0000',
    ], 'Balance After')
    ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Order ID (for usage)')
    ->addColumn('admin_user_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Admin User ID')
    ->addColumn('comment', Varien_Db_Ddl_Table::TYPE_TEXT, null, [
        'nullable' => true,
    ], 'Comment')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Created At')
    ->addIndex(
        $this->getIdxName('maho_giftcard/history', ['giftcard_id']),
        ['giftcard_id'],
    )
    ->addIndex(
        $this->getIdxName('maho_giftcard/history', ['order_id']),
        ['order_id'],
    )
    ->addForeignKey(
        $this->getFkName('maho_giftcard/history', 'giftcard_id', 'maho_giftcard/giftcard', 'giftcard_id'),
        'giftcard_id',
        $this->getTable('maho_giftcard/giftcard'),
        'giftcard_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $this->getFkName('maho_giftcard/history', 'order_id', 'sales/order', 'entity_id'),
        'order_id',
        $this->getTable('sales/order'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_SET_NULL,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Gift Card History Table');

$connection->createTable($table);

// ============================================================================
// Create scheduled email table
// ============================================================================
$table = $connection->newTable($this->getTable('maho_giftcard/scheduled_email'))
    ->addColumn('scheduled_email_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Scheduled Email ID')
    ->addColumn('giftcard_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Gift Card ID')
    ->addColumn('recipient_email', Varien_Db_Ddl_Table::TYPE_TEXT, 255, [
        'nullable' => false,
    ], 'Recipient Email')
    ->addColumn('recipient_name', Varien_Db_Ddl_Table::TYPE_TEXT, 255, [
        'nullable' => true,
    ], 'Recipient Name')
    ->addColumn('scheduled_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Scheduled Send Time (UTC)')
    ->addColumn('status', Varien_Db_Ddl_Table::TYPE_TEXT, 20, [
        'nullable' => false,
        'default'  => 'pending',
    ], 'Status')
    ->addColumn('sent_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => true,
    ], 'Sent At')
    ->addColumn('error_message', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable' => true,
    ], 'Error Message')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Created At')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable' => false,
    ], 'Updated At')
    ->addIndex(
        $this->getIdxName('maho_giftcard/scheduled_email', ['giftcard_id']),
        ['giftcard_id'],
    )
    ->addIndex(
        $this->getIdxName('maho_giftcard/scheduled_email', ['status']),
        ['status'],
    )
    ->addIndex(
        $this->getIdxName('maho_giftcard/scheduled_email', ['scheduled_at']),
        ['scheduled_at'],
    )
    ->addForeignKey(
        $this->getFkName('maho_giftcard/scheduled_email', 'giftcard_id', 'maho_giftcard/giftcard', 'giftcard_id'),
        'giftcard_id',
        $this->getTable('maho_giftcard/giftcard'),
        'giftcard_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Gift Card Scheduled Emails');

$connection->createTable($table);

// ============================================================================
// Add gift card columns to sales tables
// ============================================================================

// Quote table
$connection->addColumn($this->getTable('sales/quote'), 'giftcard_codes', [
    'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
    'nullable' => true,
    'comment'  => 'Applied Gift Card Codes (JSON)',
]);

$connection->addColumn($this->getTable('sales/quote'), 'giftcard_amount', [
    'type'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Gift Card Discount Amount',
]);

$connection->addColumn($this->getTable('sales/quote'), 'base_giftcard_amount', [
    'type'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Base Gift Card Discount Amount',
]);

// Order table
$connection->addColumn($this->getTable('sales/order'), 'giftcard_codes', [
    'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
    'nullable' => true,
    'comment'  => 'Applied Gift Card Codes (JSON)',
]);

$connection->addColumn($this->getTable('sales/order'), 'giftcard_amount', [
    'type'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Gift Card Discount Amount',
]);

$connection->addColumn($this->getTable('sales/order'), 'base_giftcard_amount', [
    'type'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Base Gift Card Discount Amount',
]);

// Order address table
$connection->addColumn($this->getTable('sales/order_address'), 'giftcard_codes', [
    'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
    'nullable' => true,
    'comment'  => 'Gift Card Codes (JSON)',
]);

$connection->addColumn($this->getTable('sales/order_address'), 'giftcard_amount', [
    'type'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Gift Card Amount',
]);

$connection->addColumn($this->getTable('sales/order_address'), 'base_giftcard_amount', [
    'type'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Base Gift Card Amount',
]);

// Invoice table
$connection->addColumn($this->getTable('sales/invoice'), 'giftcard_amount', [
    'type'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Gift Card Amount',
]);

$connection->addColumn($this->getTable('sales/invoice'), 'base_giftcard_amount', [
    'type'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Base Gift Card Amount',
]);

// Credit memo table
$connection->addColumn($this->getTable('sales/creditmemo'), 'giftcard_amount', [
    'type'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
    'length'   => '12,4',
    'nullable' => true,
    'default'  => '0.0000',
    'comment'  => 'Gift Card Amount',
]);

$connection->addColumn($this->getTable('sales/creditmemo'), 'base_giftcard_amount', [
    'type'     => Varien_Db_Ddl_Table::TYPE_DECIMAL,
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

$this->endSetup();
