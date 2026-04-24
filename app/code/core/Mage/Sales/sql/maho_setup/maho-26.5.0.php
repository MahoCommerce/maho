<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Force explicit DEFAULT on TYPE_TIMESTAMP columns originally declared without one.
// On MySQL with `explicit_defaults_for_timestamp = OFF` such TIMESTAMP NOT NULL columns
// silently receive `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`, which is
// engine-specific (PgSQL/SQLite don't do it). See issue #857.
if ($installer->getConnection() instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/billing_agreement'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/billing_agreement'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/creditmemo'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/creditmemo'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/creditmemo_comment'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/creditmemo_grid'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/creditmemo_grid'),
        'order_created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Order Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/invoice'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/invoice'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/invoice_comment'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/invoice_grid'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/invoice_grid'),
        'order_created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Order Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/order'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/order'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/order_grid'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/order_grid'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/order_item'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/order_item'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/order_status_history'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/payment_transaction'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/quote'),
        'converted_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Converted At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/quote'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/quote'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/quote_address'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/quote_address'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/quote_address_item'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/quote_address_item'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/quote_address_shipping_rate'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/quote_address_shipping_rate'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/quote_item'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/quote_item'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/quote_payment'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/quote_payment'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/recurring_profile'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/recurring_profile'),
        'start_datetime',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Start Datetime',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/recurring_profile'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/shipment'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/shipment'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/shipment_comment'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/shipment_grid'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/shipment_grid'),
        'order_created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Order Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/shipment_track'),
        'created_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Created At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('sales/shipment_track'),
        'updated_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Updated At',
        ],
    );
}

$installer->endSetup();
