<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    // sales_flat_order
    // upgrade-1.6.0.6-1.6.0.7: added coupon_rule_name
    // upgrade-1.6.0.10-1.6.0.11: added IDX_SALES_FLAT_ORDER_CUSTOMER_EMAIL (prefix index on MySQL)
    $order = $schema->createTable('sales_flat_order');
    $order->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $order->addColumn('state', Types::STRING, ['length' => 32, 'notnull' => false]);
    $order->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => false]);
    $order->addColumn('coupon_code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('protect_code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('shipping_description', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('is_virtual', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $order->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $order->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $order->addColumn('base_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_discount_canceled', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_discount_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_discount_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_shipping_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_shipping_canceled', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_shipping_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_shipping_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_shipping_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_shipping_tax_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_subtotal', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_subtotal_canceled', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_subtotal_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_subtotal_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_tax_canceled', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_tax_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_tax_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_to_global_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_to_order_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_total_canceled', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_total_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_total_invoiced_cost', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_total_offline_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_total_online_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_total_paid', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_total_qty_ordered', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_total_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('discount_canceled', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('discount_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('discount_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('shipping_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('shipping_canceled', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('shipping_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('shipping_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('shipping_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('shipping_tax_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('store_to_base_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('store_to_order_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('subtotal', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('subtotal_canceled', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('subtotal_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('subtotal_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('tax_canceled', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('tax_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('tax_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('total_canceled', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('total_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('total_offline_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('total_online_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('total_paid', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('total_qty_ordered', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('total_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('can_ship_partially', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $order->addColumn('can_ship_partially_item', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $order->addColumn('customer_is_guest', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $order->addColumn('customer_note_notify', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $order->addColumn('billing_address_id', Types::INTEGER, ['notnull' => false]);
    $order->addColumn('customer_group_id', Types::SMALLINT, ['notnull' => false]);
    $order->addColumn('edit_increment', Types::INTEGER, ['notnull' => false]);
    $order->addColumn('email_sent', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $order->addColumn('forced_shipment_with_invoice', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $order->addColumn('payment_auth_expiration', Types::INTEGER, ['notnull' => false]);
    $order->addColumn('quote_address_id', Types::INTEGER, ['notnull' => false]);
    $order->addColumn('quote_id', Types::INTEGER, ['notnull' => false]);
    $order->addColumn('shipping_address_id', Types::INTEGER, ['notnull' => false]);
    $order->addColumn('adjustment_negative', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('adjustment_positive', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_adjustment_negative', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_adjustment_positive', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_shipping_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_subtotal_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_total_due', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('payment_authorization_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('shipping_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('subtotal_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('total_due', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('weight', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('customer_dob', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $order->addColumn('increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $order->addColumn('applied_rule_ids', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('base_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $order->addColumn('customer_email', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('customer_firstname', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('customer_lastname', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('customer_middlename', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('customer_prefix', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('customer_suffix', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('customer_taxvat', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('discount_description', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('ext_customer_id', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('ext_order_id', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('global_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $order->addColumn('hold_before_state', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('hold_before_status', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('order_currency_code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('original_increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $order->addColumn('relation_child_id', Types::STRING, ['length' => 32, 'notnull' => false]);
    $order->addColumn('relation_child_real_id', Types::STRING, ['length' => 32, 'notnull' => false]);
    $order->addColumn('relation_parent_id', Types::STRING, ['length' => 32, 'notnull' => false]);
    $order->addColumn('relation_parent_real_id', Types::STRING, ['length' => 32, 'notnull' => false]);
    $order->addColumn('remote_ip', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('shipping_method', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('store_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $order->addColumn('store_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('x_forwarded_for', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addColumn('customer_note', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $order->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $order->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $order->addColumn('total_item_count', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $order->addColumn('customer_gender', Types::INTEGER, ['notnull' => false]);
    $order->addColumn('hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('shipping_hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_shipping_hidden_tax_amnt', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('hidden_tax_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_hidden_tax_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('hidden_tax_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_hidden_tax_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('shipping_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('base_shipping_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $order->addColumn('coupon_rule_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $order->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $order->addIndex(['status'], 'idx_sales_flat_order_status');
    $order->addIndex(['state'], 'idx_sales_flat_order_state');
    $order->addIndex(['store_id'], 'idx_sales_flat_order_store_id');
    $order->addUniqueIndex(['increment_id'], 'unq_sales_flat_order_increment_id');
    $order->addIndex(['created_at'], 'idx_sales_flat_order_created_at');
    $order->addIndex(['customer_id'], 'idx_sales_flat_order_customer_id');
    $order->addIndex(['ext_order_id'], 'idx_sales_flat_order_ext_order_id');
    $order->addIndex(['quote_id'], 'idx_sales_flat_order_quote_id');
    $order->addIndex(['updated_at'], 'idx_sales_flat_order_updated_at');
    $order->addIndex(['customer_email'], 'idx_sales_flat_order_customer_email');
    $order->addForeignKeyConstraint('customer_entity', ['customer_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_sales_flat_order_customer');
    $order->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_sales_flat_order_store');
    $order->setComment('Sales Flat Order');

    // sales_flat_order_grid
    $orderGrid = $schema->createTable('sales_flat_order_grid');
    $orderGrid->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $orderGrid->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => false]);
    $orderGrid->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $orderGrid->addColumn('store_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderGrid->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $orderGrid->addColumn('base_grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderGrid->addColumn('base_total_paid', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderGrid->addColumn('grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderGrid->addColumn('total_paid', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderGrid->addColumn('increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $orderGrid->addColumn('base_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $orderGrid->addColumn('order_currency_code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderGrid->addColumn('shipping_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderGrid->addColumn('billing_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderGrid->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $orderGrid->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $orderGrid->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $orderGrid->addIndex(['status'], 'idx_sales_flat_order_grid_status');
    $orderGrid->addIndex(['store_id'], 'idx_sales_flat_order_grid_store_id');
    $orderGrid->addIndex(['base_grand_total'], 'idx_sales_flat_order_grid_base_grand_total');
    $orderGrid->addIndex(['base_total_paid'], 'idx_sales_flat_order_grid_base_total_paid');
    $orderGrid->addIndex(['grand_total'], 'idx_sales_flat_order_grid_grand_total');
    $orderGrid->addIndex(['total_paid'], 'idx_sales_flat_order_grid_total_paid');
    $orderGrid->addUniqueIndex(['increment_id'], 'unq_sales_flat_order_grid_increment_id');
    $orderGrid->addIndex(['shipping_name'], 'idx_sales_flat_order_grid_shipping_name');
    $orderGrid->addIndex(['billing_name'], 'idx_sales_flat_order_grid_billing_name');
    $orderGrid->addIndex(['created_at'], 'idx_sales_flat_order_grid_created_at');
    $orderGrid->addIndex(['customer_id'], 'idx_sales_flat_order_grid_customer_id');
    $orderGrid->addIndex(['updated_at'], 'idx_sales_flat_order_grid_updated_at');
    $orderGrid->addForeignKeyConstraint('customer_entity', ['customer_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_sales_flat_order_grid_customer');
    $orderGrid->addForeignKeyConstraint('sales_flat_order', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_order_grid_order');
    $orderGrid->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_sales_flat_order_grid_store');
    $orderGrid->setComment('Sales Flat Order Grid');

    // sales_flat_order_address
    // upgrade-1.6.0.5-1.6.0.6: added vat_id, vat_is_valid, vat_request_id, vat_request_date, vat_request_success
    $orderAddress = $schema->createTable('sales_flat_order_address');
    $orderAddress->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $orderAddress->addColumn('parent_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $orderAddress->addColumn('customer_address_id', Types::INTEGER, ['notnull' => false]);
    $orderAddress->addColumn('quote_address_id', Types::INTEGER, ['notnull' => false]);
    $orderAddress->addColumn('region_id', Types::INTEGER, ['notnull' => false]);
    $orderAddress->addColumn('customer_id', Types::INTEGER, ['notnull' => false]);
    $orderAddress->addColumn('fax', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('region', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('postcode', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('lastname', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('street', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('city', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('email', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('telephone', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('country_id', Types::STRING, ['length' => 2, 'notnull' => false]);
    $orderAddress->addColumn('firstname', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('address_type', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('prefix', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('middlename', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('suffix', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('company', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('vat_id', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('vat_is_valid', Types::SMALLINT, ['notnull' => false]);
    $orderAddress->addColumn('vat_request_id', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('vat_request_date', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderAddress->addColumn('vat_request_success', Types::SMALLINT, ['notnull' => false]);
    $orderAddress->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $orderAddress->addIndex(['parent_id'], 'idx_sales_flat_order_address_parent_id');
    $orderAddress->addForeignKeyConstraint('sales_flat_order', ['parent_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_order_address_parent');
    $orderAddress->setComment('Sales Flat Order Address');

    // sales_flat_order_status_history
    // upgrade-1.6.0.0-1.6.0.1: added entity_name
    $orderStatusHistory = $schema->createTable('sales_flat_order_status_history');
    $orderStatusHistory->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $orderStatusHistory->addColumn('parent_id', Types::INTEGER, ['unsigned' => true]);
    $orderStatusHistory->addColumn('is_customer_notified', Types::INTEGER, ['notnull' => false]);
    $orderStatusHistory->addColumn('is_visible_on_front', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $orderStatusHistory->addColumn('comment', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $orderStatusHistory->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => false]);
    $orderStatusHistory->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $orderStatusHistory->addColumn('entity_name', Types::STRING, ['length' => 32, 'notnull' => false]);
    $orderStatusHistory->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $orderStatusHistory->addIndex(['parent_id'], 'idx_sales_flat_order_status_history_parent_id');
    $orderStatusHistory->addIndex(['created_at'], 'idx_sales_flat_order_status_history_created_at');
    $orderStatusHistory->addForeignKeyConstraint('sales_flat_order', ['parent_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_order_status_history_parent');
    $orderStatusHistory->setComment('Sales Flat Order Status History');

    // sales_flat_order_item
    // upgrade-1.6.0.4-1.6.0.5: added base_tax_refunded, discount_refunded, base_discount_refunded
    // upgrade-1.6.0.10-1.6.0.11: added IDX_SALES_FLAT_ORDER_ITEM_PRODUCT_ID
    // upgrade-1.6.0.11-1.6.0.12: created_at/updated_at default CURRENT_TIMESTAMP
    $orderItem = $schema->createTable('sales_flat_order_item');
    $orderItem->addColumn('item_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $orderItem->addColumn('order_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $orderItem->addColumn('parent_item_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $orderItem->addColumn('quote_item_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $orderItem->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $orderItem->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $orderItem->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $orderItem->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $orderItem->addColumn('product_type', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderItem->addColumn('product_options', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $orderItem->addColumn('weight', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('is_virtual', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $orderItem->addColumn('sku', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderItem->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderItem->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $orderItem->addColumn('applied_rule_ids', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $orderItem->addColumn('additional_data', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $orderItem->addColumn('free_shipping', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $orderItem->addColumn('is_qty_decimal', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $orderItem->addColumn('no_discount', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $orderItem->addColumn('qty_backordered', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('qty_canceled', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('qty_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('qty_ordered', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('qty_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('qty_shipped', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('base_cost', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $orderItem->addColumn('base_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $orderItem->addColumn('original_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('base_original_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('tax_percent', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('base_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('tax_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('base_tax_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('discount_percent', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('base_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('discount_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('base_discount_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('amount_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('base_amount_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('row_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $orderItem->addColumn('base_row_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $orderItem->addColumn('row_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $orderItem->addColumn('base_row_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $orderItem->addColumn('row_weight', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $orderItem->addColumn('base_tax_before_discount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('tax_before_discount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('ext_order_item_id', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderItem->addColumn('locked_do_invoice', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $orderItem->addColumn('locked_do_ship', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $orderItem->addColumn('price_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('base_price_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('row_total_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('base_row_total_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('base_hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('hidden_tax_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('base_hidden_tax_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('hidden_tax_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('base_hidden_tax_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('is_nominal', Types::INTEGER, ['default' => 0]);
    $orderItem->addColumn('tax_canceled', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('hidden_tax_canceled', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('tax_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('base_tax_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('discount_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addColumn('base_discount_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderItem->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('item_id')->create(),
    );
    $orderItem->addIndex(['order_id'], 'idx_sales_flat_order_item_order_id');
    $orderItem->addIndex(['store_id'], 'idx_sales_flat_order_item_store_id');
    $orderItem->addIndex(['product_id'], 'idx_sales_flat_order_item_product_id');
    $orderItem->addForeignKeyConstraint('sales_flat_order', ['order_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_order_item_order');
    $orderItem->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_sales_flat_order_item_store');
    $orderItem->setComment('Sales Flat Order Item');

    // sales_flat_order_payment
    // Note: Maho_Paypal grafts paypal_order_id column + index on this table.
    $orderPayment = $schema->createTable('sales_flat_order_payment');
    $orderPayment->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $orderPayment->addColumn('parent_id', Types::INTEGER, ['unsigned' => true]);
    $orderPayment->addColumn('base_shipping_captured', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('shipping_captured', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('amount_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('base_amount_paid', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('amount_canceled', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('base_amount_authorized', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('base_amount_paid_online', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('base_amount_refunded_online', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('base_shipping_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('shipping_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('amount_paid', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('amount_authorized', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('base_amount_ordered', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('base_shipping_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('shipping_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('base_amount_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('amount_ordered', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('base_amount_canceled', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderPayment->addColumn('quote_payment_id', Types::INTEGER, ['notnull' => false]);
    $orderPayment->addColumn('additional_data', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $orderPayment->addColumn('cc_exp_month', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_ss_start_year', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('echeck_bank_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('method', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_debug_request_body', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_secure_verify', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('protection_eligibility', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_approval', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_last4', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_status_description', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('echeck_type', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_debug_response_serialized', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_ss_start_month', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('echeck_account_type', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('last_trans_id', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_cid_status', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_owner', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_type', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('po_number', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_exp_year', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_status', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('echeck_routing_number', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('account_status', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('anet_trans_method', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_debug_response_body', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_ss_issue', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('echeck_account_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_avs_status', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_number_enc', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('cc_trans_id', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('paybox_request_number', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('address_status', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderPayment->addColumn('additional_information', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $orderPayment->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $orderPayment->addIndex(['parent_id'], 'idx_sales_flat_order_payment_parent_id');
    $orderPayment->addForeignKeyConstraint('sales_flat_order', ['parent_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_order_payment_parent');
    $orderPayment->setComment('Sales Flat Order Payment');

    // sales_flat_shipment
    // upgrade-1.6.0.1-1.6.0.2: added packages (TEXT length 20000)
    // upgrade-1.6.0.2-1.6.0.3: added shipping_label (VARBINARY/BLOB length 2m)
    $shipment = $schema->createTable('sales_flat_shipment');
    $shipment->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $shipment->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $shipment->addColumn('total_weight', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $shipment->addColumn('total_qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $shipment->addColumn('email_sent', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $shipment->addColumn('order_id', Types::INTEGER, ['unsigned' => true]);
    $shipment->addColumn('customer_id', Types::INTEGER, ['notnull' => false]);
    $shipment->addColumn('shipping_address_id', Types::INTEGER, ['notnull' => false]);
    $shipment->addColumn('billing_address_id', Types::INTEGER, ['notnull' => false]);
    $shipment->addColumn('shipment_status', Types::INTEGER, ['notnull' => false]);
    $shipment->addColumn('increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $shipment->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $shipment->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $shipment->addColumn('packages', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $shipment->addColumn('shipping_label', Types::BLOB, ['length' => 2097152, 'notnull' => false]);
    $shipment->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $shipment->addIndex(['store_id'], 'idx_sales_flat_shipment_store_id');
    $shipment->addIndex(['total_qty'], 'idx_sales_flat_shipment_total_qty');
    $shipment->addUniqueIndex(['increment_id'], 'unq_sales_flat_shipment_increment_id');
    $shipment->addIndex(['order_id'], 'idx_sales_flat_shipment_order_id');
    $shipment->addIndex(['created_at'], 'idx_sales_flat_shipment_created_at');
    $shipment->addIndex(['updated_at'], 'idx_sales_flat_shipment_updated_at');
    $shipment->addForeignKeyConstraint('sales_flat_order', ['order_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_shipment_order');
    $shipment->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_sales_flat_shipment_store');
    $shipment->setComment('Sales Flat Shipment');

    // sales_flat_shipment_grid
    $shipmentGrid = $schema->createTable('sales_flat_shipment_grid');
    $shipmentGrid->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $shipmentGrid->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $shipmentGrid->addColumn('total_qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $shipmentGrid->addColumn('order_id', Types::INTEGER, ['unsigned' => true]);
    $shipmentGrid->addColumn('shipment_status', Types::INTEGER, ['notnull' => false]);
    $shipmentGrid->addColumn('increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $shipmentGrid->addColumn('order_increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $shipmentGrid->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $shipmentGrid->addColumn('order_created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $shipmentGrid->addColumn('shipping_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $shipmentGrid->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $shipmentGrid->addIndex(['store_id'], 'idx_sales_flat_shipment_grid_store_id');
    $shipmentGrid->addIndex(['total_qty'], 'idx_sales_flat_shipment_grid_total_qty');
    $shipmentGrid->addIndex(['order_id'], 'idx_sales_flat_shipment_grid_order_id');
    $shipmentGrid->addIndex(['shipment_status'], 'idx_sales_flat_shipment_grid_shipment_status');
    $shipmentGrid->addUniqueIndex(['increment_id'], 'unq_sales_flat_shipment_grid_increment_id');
    $shipmentGrid->addIndex(['order_increment_id'], 'idx_sales_flat_shipment_grid_order_increment_id');
    $shipmentGrid->addIndex(['created_at'], 'idx_sales_flat_shipment_grid_created_at');
    $shipmentGrid->addIndex(['order_created_at'], 'idx_sales_flat_shipment_grid_order_created_at');
    $shipmentGrid->addIndex(['shipping_name'], 'idx_sales_flat_shipment_grid_shipping_name');
    $shipmentGrid->addForeignKeyConstraint('sales_flat_shipment', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_shipment_grid_shipment');
    $shipmentGrid->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_sales_flat_shipment_grid_store');
    $shipmentGrid->setComment('Sales Flat Shipment Grid');

    // sales_flat_shipment_item
    $shipmentItem = $schema->createTable('sales_flat_shipment_item');
    $shipmentItem->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $shipmentItem->addColumn('parent_id', Types::INTEGER, ['unsigned' => true]);
    $shipmentItem->addColumn('row_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $shipmentItem->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $shipmentItem->addColumn('weight', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $shipmentItem->addColumn('qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $shipmentItem->addColumn('product_id', Types::INTEGER, ['notnull' => false]);
    $shipmentItem->addColumn('order_item_id', Types::INTEGER, ['notnull' => false]);
    $shipmentItem->addColumn('additional_data', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $shipmentItem->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $shipmentItem->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $shipmentItem->addColumn('sku', Types::STRING, ['length' => 255, 'notnull' => false]);
    $shipmentItem->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $shipmentItem->addIndex(['parent_id'], 'idx_sales_flat_shipment_item_parent_id');
    $shipmentItem->addForeignKeyConstraint('sales_flat_shipment', ['parent_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_shipment_item_parent');
    $shipmentItem->setComment('Sales Flat Shipment Item');

    // sales_flat_shipment_track
    $shipmentTrack = $schema->createTable('sales_flat_shipment_track');
    $shipmentTrack->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $shipmentTrack->addColumn('parent_id', Types::INTEGER, ['unsigned' => true]);
    $shipmentTrack->addColumn('weight', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $shipmentTrack->addColumn('qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $shipmentTrack->addColumn('order_id', Types::INTEGER, ['unsigned' => true]);
    $shipmentTrack->addColumn('track_number', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $shipmentTrack->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $shipmentTrack->addColumn('title', Types::STRING, ['length' => 255, 'notnull' => false]);
    $shipmentTrack->addColumn('carrier_code', Types::STRING, ['length' => 32, 'notnull' => false]);
    $shipmentTrack->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $shipmentTrack->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $shipmentTrack->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $shipmentTrack->addIndex(['parent_id'], 'idx_sales_flat_shipment_track_parent_id');
    $shipmentTrack->addIndex(['order_id'], 'idx_sales_flat_shipment_track_order_id');
    $shipmentTrack->addIndex(['created_at'], 'idx_sales_flat_shipment_track_created_at');
    $shipmentTrack->addForeignKeyConstraint('sales_flat_shipment', ['parent_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_shipment_track_parent');
    $shipmentTrack->setComment('Sales Flat Shipment Track');

    // sales_flat_shipment_comment
    $shipmentComment = $schema->createTable('sales_flat_shipment_comment');
    $shipmentComment->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $shipmentComment->addColumn('parent_id', Types::INTEGER, ['unsigned' => true]);
    $shipmentComment->addColumn('is_customer_notified', Types::INTEGER, ['notnull' => false]);
    $shipmentComment->addColumn('is_visible_on_front', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $shipmentComment->addColumn('comment', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $shipmentComment->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $shipmentComment->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $shipmentComment->addIndex(['created_at'], 'idx_sales_flat_shipment_comment_created_at');
    $shipmentComment->addIndex(['parent_id'], 'idx_sales_flat_shipment_comment_parent_id');
    $shipmentComment->addForeignKeyConstraint('sales_flat_shipment', ['parent_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_shipment_comment_parent');
    $shipmentComment->setComment('Sales Flat Shipment Comment');

    // sales_flat_invoice
    // upgrade-1.6.0.7-1.6.0.8: added discount_description
    // Note: Maho_Giftcard grafts giftcard_amount + base_giftcard_amount columns on this table.
    $invoice = $schema->createTable('sales_flat_invoice');
    $invoice->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $invoice->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $invoice->addColumn('base_grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('shipping_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('base_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('store_to_order_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('base_shipping_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('base_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('base_to_order_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('shipping_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('subtotal_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('base_subtotal_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('store_to_base_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('base_shipping_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('total_qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('base_to_global_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('subtotal', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('base_subtotal', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('billing_address_id', Types::INTEGER, ['notnull' => false]);
    $invoice->addColumn('is_used_for_refund', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $invoice->addColumn('order_id', Types::INTEGER, ['unsigned' => true]);
    $invoice->addColumn('email_sent', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $invoice->addColumn('can_void_flag', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $invoice->addColumn('state', Types::INTEGER, ['notnull' => false]);
    $invoice->addColumn('shipping_address_id', Types::INTEGER, ['notnull' => false]);
    $invoice->addColumn('store_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $invoice->addColumn('transaction_id', Types::STRING, ['length' => 255, 'notnull' => false]);
    $invoice->addColumn('order_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $invoice->addColumn('base_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $invoice->addColumn('global_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $invoice->addColumn('increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $invoice->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $invoice->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $invoice->addColumn('hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('base_hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('shipping_hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('base_shipping_hidden_tax_amnt', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('shipping_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('base_shipping_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('base_total_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoice->addColumn('discount_description', Types::STRING, ['length' => 255, 'notnull' => false]);
    $invoice->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $invoice->addIndex(['store_id'], 'idx_sales_flat_invoice_store_id');
    $invoice->addIndex(['grand_total'], 'idx_sales_flat_invoice_grand_total');
    $invoice->addIndex(['order_id'], 'idx_sales_flat_invoice_order_id');
    $invoice->addIndex(['state'], 'idx_sales_flat_invoice_state');
    $invoice->addUniqueIndex(['increment_id'], 'unq_sales_flat_invoice_increment_id');
    $invoice->addIndex(['created_at'], 'idx_sales_flat_invoice_created_at');
    $invoice->addForeignKeyConstraint('sales_flat_order', ['order_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_invoice_order');
    $invoice->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_sales_flat_invoice_store');
    $invoice->setComment('Sales Flat Invoice');

    // sales_flat_invoice_grid
    $invoiceGrid = $schema->createTable('sales_flat_invoice_grid');
    $invoiceGrid->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $invoiceGrid->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $invoiceGrid->addColumn('base_grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceGrid->addColumn('grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceGrid->addColumn('order_id', Types::INTEGER, ['unsigned' => true]);
    $invoiceGrid->addColumn('state', Types::INTEGER, ['notnull' => false]);
    $invoiceGrid->addColumn('store_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $invoiceGrid->addColumn('order_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $invoiceGrid->addColumn('base_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $invoiceGrid->addColumn('global_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $invoiceGrid->addColumn('increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $invoiceGrid->addColumn('order_increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $invoiceGrid->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $invoiceGrid->addColumn('order_created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $invoiceGrid->addColumn('billing_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $invoiceGrid->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $invoiceGrid->addIndex(['store_id'], 'idx_sales_flat_invoice_grid_store_id');
    $invoiceGrid->addIndex(['grand_total'], 'idx_sales_flat_invoice_grid_grand_total');
    $invoiceGrid->addIndex(['order_id'], 'idx_sales_flat_invoice_grid_order_id');
    $invoiceGrid->addIndex(['state'], 'idx_sales_flat_invoice_grid_state');
    $invoiceGrid->addUniqueIndex(['increment_id'], 'unq_sales_flat_invoice_grid_increment_id');
    $invoiceGrid->addIndex(['order_increment_id'], 'idx_sales_flat_invoice_grid_order_increment_id');
    $invoiceGrid->addIndex(['created_at'], 'idx_sales_flat_invoice_grid_created_at');
    $invoiceGrid->addIndex(['order_created_at'], 'idx_sales_flat_invoice_grid_order_created_at');
    $invoiceGrid->addIndex(['billing_name'], 'idx_sales_flat_invoice_grid_billing_name');
    $invoiceGrid->addForeignKeyConstraint('sales_flat_invoice', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_invoice_grid_invoice');
    $invoiceGrid->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_sales_flat_invoice_grid_store');
    $invoiceGrid->setComment('Sales Flat Invoice Grid');

    // sales_flat_invoice_item
    $invoiceItem = $schema->createTable('sales_flat_invoice_item');
    $invoiceItem->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $invoiceItem->addColumn('parent_id', Types::INTEGER, ['unsigned' => true]);
    $invoiceItem->addColumn('base_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addColumn('tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addColumn('base_row_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addColumn('discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addColumn('row_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addColumn('base_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addColumn('price_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addColumn('base_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addColumn('base_price_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addColumn('qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addColumn('base_cost', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addColumn('base_row_total_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addColumn('row_total_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addColumn('product_id', Types::INTEGER, ['notnull' => false]);
    $invoiceItem->addColumn('order_item_id', Types::INTEGER, ['notnull' => false]);
    $invoiceItem->addColumn('additional_data', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $invoiceItem->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $invoiceItem->addColumn('sku', Types::STRING, ['length' => 255, 'notnull' => false]);
    $invoiceItem->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $invoiceItem->addColumn('hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addColumn('base_hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $invoiceItem->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $invoiceItem->addIndex(['parent_id'], 'idx_sales_flat_invoice_item_parent_id');
    $invoiceItem->addForeignKeyConstraint('sales_flat_invoice', ['parent_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_invoice_item_parent');
    $invoiceItem->setComment('Sales Flat Invoice Item');

    // sales_flat_invoice_comment
    $invoiceComment = $schema->createTable('sales_flat_invoice_comment');
    $invoiceComment->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $invoiceComment->addColumn('parent_id', Types::INTEGER, ['unsigned' => true]);
    $invoiceComment->addColumn('is_customer_notified', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $invoiceComment->addColumn('is_visible_on_front', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $invoiceComment->addColumn('comment', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $invoiceComment->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $invoiceComment->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $invoiceComment->addIndex(['created_at'], 'idx_sales_flat_invoice_comment_created_at');
    $invoiceComment->addIndex(['parent_id'], 'idx_sales_flat_invoice_comment_parent_id');
    $invoiceComment->addForeignKeyConstraint('sales_flat_invoice', ['parent_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_invoice_comment_parent');
    $invoiceComment->setComment('Sales Flat Invoice Comment');

    // sales_flat_creditmemo
    // upgrade-1.6.0.7-1.6.0.8: added discount_description
    // Note: Maho_Giftcard grafts giftcard_amount + base_giftcard_amount columns on this table.
    $creditmemo = $schema->createTable('sales_flat_creditmemo');
    $creditmemo->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $creditmemo->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $creditmemo->addColumn('adjustment_positive', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('base_shipping_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('store_to_order_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('base_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('base_to_order_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('base_adjustment_negative', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('base_subtotal_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('shipping_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('subtotal_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('adjustment_negative', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('base_shipping_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('store_to_base_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('base_to_global_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('base_adjustment', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('base_subtotal', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('subtotal', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('adjustment', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('base_grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('base_adjustment_positive', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('base_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('shipping_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('order_id', Types::INTEGER, ['unsigned' => true]);
    $creditmemo->addColumn('email_sent', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $creditmemo->addColumn('creditmemo_status', Types::INTEGER, ['notnull' => false]);
    $creditmemo->addColumn('state', Types::INTEGER, ['notnull' => false]);
    $creditmemo->addColumn('shipping_address_id', Types::INTEGER, ['notnull' => false]);
    $creditmemo->addColumn('billing_address_id', Types::INTEGER, ['notnull' => false]);
    $creditmemo->addColumn('invoice_id', Types::INTEGER, ['notnull' => false]);
    $creditmemo->addColumn('store_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $creditmemo->addColumn('order_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $creditmemo->addColumn('base_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $creditmemo->addColumn('global_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $creditmemo->addColumn('transaction_id', Types::STRING, ['length' => 255, 'notnull' => false]);
    $creditmemo->addColumn('increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $creditmemo->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $creditmemo->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $creditmemo->addColumn('hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('base_hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('shipping_hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('base_shipping_hidden_tax_amnt', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('shipping_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('base_shipping_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemo->addColumn('discount_description', Types::STRING, ['length' => 255, 'notnull' => false]);
    $creditmemo->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $creditmemo->addIndex(['store_id'], 'idx_sales_flat_creditmemo_store_id');
    $creditmemo->addIndex(['order_id'], 'idx_sales_flat_creditmemo_order_id');
    $creditmemo->addIndex(['creditmemo_status'], 'idx_sales_flat_creditmemo_creditmemo_status');
    $creditmemo->addUniqueIndex(['increment_id'], 'unq_sales_flat_creditmemo_increment_id');
    $creditmemo->addIndex(['state'], 'idx_sales_flat_creditmemo_state');
    $creditmemo->addIndex(['created_at'], 'idx_sales_flat_creditmemo_created_at');
    $creditmemo->addForeignKeyConstraint('sales_flat_order', ['order_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_creditmemo_order');
    $creditmemo->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_sales_flat_creditmemo_store');
    $creditmemo->setComment('Sales Flat Creditmemo');

    // sales_flat_creditmemo_grid
    $creditmemoGrid = $schema->createTable('sales_flat_creditmemo_grid');
    $creditmemoGrid->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $creditmemoGrid->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $creditmemoGrid->addColumn('store_to_order_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoGrid->addColumn('base_to_order_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoGrid->addColumn('grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoGrid->addColumn('store_to_base_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoGrid->addColumn('base_to_global_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoGrid->addColumn('base_grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoGrid->addColumn('order_id', Types::INTEGER, ['unsigned' => true]);
    $creditmemoGrid->addColumn('creditmemo_status', Types::INTEGER, ['notnull' => false]);
    $creditmemoGrid->addColumn('state', Types::INTEGER, ['notnull' => false]);
    $creditmemoGrid->addColumn('invoice_id', Types::INTEGER, ['notnull' => false]);
    $creditmemoGrid->addColumn('store_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $creditmemoGrid->addColumn('order_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $creditmemoGrid->addColumn('base_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $creditmemoGrid->addColumn('global_currency_code', Types::STRING, ['length' => 3, 'notnull' => false]);
    $creditmemoGrid->addColumn('increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $creditmemoGrid->addColumn('order_increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $creditmemoGrid->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $creditmemoGrid->addColumn('order_created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $creditmemoGrid->addColumn('billing_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $creditmemoGrid->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $creditmemoGrid->addIndex(['store_id'], 'idx_sales_flat_creditmemo_grid_store_id');
    $creditmemoGrid->addIndex(['grand_total'], 'idx_sales_flat_creditmemo_grid_grand_total');
    $creditmemoGrid->addIndex(['base_grand_total'], 'idx_sales_flat_creditmemo_grid_base_grand_total');
    $creditmemoGrid->addIndex(['order_id'], 'idx_sales_flat_creditmemo_grid_order_id');
    $creditmemoGrid->addIndex(['creditmemo_status'], 'idx_sales_flat_creditmemo_grid_creditmemo_status');
    $creditmemoGrid->addIndex(['state'], 'idx_sales_flat_creditmemo_grid_state');
    $creditmemoGrid->addUniqueIndex(['increment_id'], 'unq_sales_flat_creditmemo_grid_increment_id');
    $creditmemoGrid->addIndex(['order_increment_id'], 'idx_sales_flat_creditmemo_grid_order_increment_id');
    $creditmemoGrid->addIndex(['created_at'], 'idx_sales_flat_creditmemo_grid_created_at');
    $creditmemoGrid->addIndex(['order_created_at'], 'idx_sales_flat_creditmemo_grid_order_created_at');
    $creditmemoGrid->addIndex(['billing_name'], 'idx_sales_flat_creditmemo_grid_billing_name');
    $creditmemoGrid->addForeignKeyConstraint('sales_flat_creditmemo', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_creditmemo_grid_creditmemo');
    $creditmemoGrid->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_sales_flat_creditmemo_grid_store');
    $creditmemoGrid->setComment('Sales Flat Creditmemo Grid');

    // sales_flat_creditmemo_item
    $creditmemoItem = $schema->createTable('sales_flat_creditmemo_item');
    $creditmemoItem->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $creditmemoItem->addColumn('parent_id', Types::INTEGER, ['unsigned' => true]);
    $creditmemoItem->addColumn('base_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addColumn('tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addColumn('base_row_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addColumn('discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addColumn('row_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addColumn('base_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addColumn('price_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addColumn('base_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addColumn('base_price_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addColumn('qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addColumn('base_cost', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addColumn('base_row_total_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addColumn('row_total_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addColumn('product_id', Types::INTEGER, ['notnull' => false]);
    $creditmemoItem->addColumn('order_item_id', Types::INTEGER, ['notnull' => false]);
    $creditmemoItem->addColumn('additional_data', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $creditmemoItem->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $creditmemoItem->addColumn('sku', Types::STRING, ['length' => 255, 'notnull' => false]);
    $creditmemoItem->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $creditmemoItem->addColumn('hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addColumn('base_hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $creditmemoItem->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $creditmemoItem->addIndex(['parent_id'], 'idx_sales_flat_creditmemo_item_parent_id');
    $creditmemoItem->addForeignKeyConstraint('sales_flat_creditmemo', ['parent_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_creditmemo_item_parent');
    $creditmemoItem->setComment('Sales Flat Creditmemo Item');

    // sales_flat_creditmemo_comment
    $creditmemoComment = $schema->createTable('sales_flat_creditmemo_comment');
    $creditmemoComment->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $creditmemoComment->addColumn('parent_id', Types::INTEGER, ['unsigned' => true]);
    $creditmemoComment->addColumn('is_customer_notified', Types::INTEGER, ['notnull' => false]);
    $creditmemoComment->addColumn('is_visible_on_front', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $creditmemoComment->addColumn('comment', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $creditmemoComment->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $creditmemoComment->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $creditmemoComment->addIndex(['created_at'], 'idx_sales_flat_creditmemo_comment_created_at');
    $creditmemoComment->addIndex(['parent_id'], 'idx_sales_flat_creditmemo_comment_parent_id');
    $creditmemoComment->addForeignKeyConstraint('sales_flat_creditmemo', ['parent_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_creditmemo_comment_parent');
    $creditmemoComment->setComment('Sales Flat Creditmemo Comment');

    // sales_flat_quote
    // upgrade-1.6.0.8-1.6.0.9: remote_ip changed to varchar(255) nullable
    // upgrade-1.6.0.11-1.6.0.12: created_at/updated_at default CURRENT_TIMESTAMP (MySQL)
    // Note: Maho_Giftcard grafts giftcard_codes / giftcard_amount / base_giftcard_amount on this table.
    $quote = $schema->createTable('sales_flat_quote');
    $quote->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $quote->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $quote->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $quote->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $quote->addColumn('converted_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $quote->addColumn('is_active', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 1]);
    $quote->addColumn('is_virtual', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $quote->addColumn('items_count', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $quote->addColumn('items_qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quote->addColumn('orig_order_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $quote->addColumn('store_to_base_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quote->addColumn('store_to_quote_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quote->addColumn('base_currency_code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quote->addColumn('store_currency_code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quote->addColumn('quote_currency_code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quote->addColumn('grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quote->addColumn('base_grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quote->addColumn('checkout_method', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quote->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $quote->addColumn('customer_tax_class_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $quote->addColumn('customer_group_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $quote->addColumn('customer_email', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quote->addColumn('customer_prefix', Types::STRING, ['length' => 40, 'notnull' => false]);
    $quote->addColumn('customer_firstname', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quote->addColumn('customer_middlename', Types::STRING, ['length' => 40, 'notnull' => false]);
    $quote->addColumn('customer_lastname', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quote->addColumn('customer_suffix', Types::STRING, ['length' => 40, 'notnull' => false]);
    $quote->addColumn('customer_dob', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $quote->addColumn('customer_note', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quote->addColumn('customer_note_notify', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 1]);
    $quote->addColumn('customer_is_guest', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $quote->addColumn('remote_ip', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quote->addColumn('applied_rule_ids', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quote->addColumn('reserved_order_id', Types::STRING, ['length' => 64, 'notnull' => false]);
    $quote->addColumn('password_hash', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quote->addColumn('coupon_code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quote->addColumn('global_currency_code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quote->addColumn('base_to_global_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quote->addColumn('base_to_quote_rate', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quote->addColumn('customer_taxvat', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quote->addColumn('customer_gender', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quote->addColumn('subtotal', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quote->addColumn('base_subtotal', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quote->addColumn('subtotal_with_discount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quote->addColumn('base_subtotal_with_discount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quote->addColumn('is_changed', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $quote->addColumn('trigger_recollect', Types::SMALLINT, ['default' => 0]);
    $quote->addColumn('ext_shipping_info', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $quote->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $quote->addIndex(['customer_id', 'store_id', 'is_active'], 'idx_sales_flat_quote_customer_id_store_id_is_active');
    $quote->addIndex(['store_id'], 'idx_sales_flat_quote_store_id');
    $quote->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_quote_store');
    $quote->setComment('Sales Flat Quote');

    // sales_flat_quote_address
    // upgrade-1.6.0.5-1.6.0.6: added vat_id, vat_is_valid, vat_request_id, vat_request_date, vat_request_success
    // upgrade-1.6.0.11-1.6.0.12: created_at/updated_at default CURRENT_TIMESTAMP (MySQL)
    $quoteAddress = $schema->createTable('sales_flat_quote_address');
    $quoteAddress->addColumn('address_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $quoteAddress->addColumn('quote_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $quoteAddress->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $quoteAddress->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $quoteAddress->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $quoteAddress->addColumn('save_in_address_book', Types::SMALLINT, ['notnull' => false, 'default' => 0]);
    $quoteAddress->addColumn('customer_address_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $quoteAddress->addColumn('address_type', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('email', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('prefix', Types::STRING, ['length' => 40, 'notnull' => false]);
    $quoteAddress->addColumn('firstname', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('middlename', Types::STRING, ['length' => 40, 'notnull' => false]);
    $quoteAddress->addColumn('lastname', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('suffix', Types::STRING, ['length' => 40, 'notnull' => false]);
    $quoteAddress->addColumn('company', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('street', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('city', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('region', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('region_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $quoteAddress->addColumn('postcode', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('country_id', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('telephone', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('fax', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('same_as_billing', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $quoteAddress->addColumn('free_shipping', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $quoteAddress->addColumn('collect_shipping_rates', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $quoteAddress->addColumn('shipping_method', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('shipping_description', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('weight', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddress->addColumn('subtotal', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddress->addColumn('base_subtotal', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddress->addColumn('subtotal_with_discount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddress->addColumn('base_subtotal_with_discount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddress->addColumn('tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddress->addColumn('base_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddress->addColumn('shipping_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddress->addColumn('base_shipping_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddress->addColumn('shipping_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddress->addColumn('base_shipping_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddress->addColumn('discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddress->addColumn('base_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddress->addColumn('grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddress->addColumn('base_grand_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddress->addColumn('customer_notes', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $quoteAddress->addColumn('applied_taxes', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $quoteAddress->addColumn('discount_description', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('shipping_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddress->addColumn('base_shipping_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddress->addColumn('subtotal_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddress->addColumn('base_subtotal_total_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddress->addColumn('hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddress->addColumn('base_hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddress->addColumn('shipping_hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddress->addColumn('base_shipping_hidden_tax_amnt', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddress->addColumn('shipping_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddress->addColumn('base_shipping_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddress->addColumn('vat_id', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('vat_is_valid', Types::SMALLINT, ['notnull' => false]);
    $quoteAddress->addColumn('vat_request_id', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('vat_request_date', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddress->addColumn('vat_request_success', Types::SMALLINT, ['notnull' => false]);
    $quoteAddress->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('address_id')->create(),
    );
    $quoteAddress->addIndex(['quote_id'], 'idx_sales_flat_quote_address_quote_id');
    $quoteAddress->addForeignKeyConstraint('sales_flat_quote', ['quote_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_quote_address_quote');
    $quoteAddress->setComment('Sales Flat Quote Address');

    // sales_flat_quote_item
    // upgrade-1.6.0.11-1.6.0.12: created_at/updated_at default CURRENT_TIMESTAMP (MySQL)
    // FK to catalog_product_entity is deferred until Mage_Catalog is converted to declarative schema.
    $quoteItem = $schema->createTable('sales_flat_quote_item');
    $quoteItem->addColumn('item_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $quoteItem->addColumn('quote_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $quoteItem->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $quoteItem->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $quoteItem->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $quoteItem->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $quoteItem->addColumn('parent_item_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $quoteItem->addColumn('is_virtual', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $quoteItem->addColumn('sku', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteItem->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteItem->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $quoteItem->addColumn('applied_rule_ids', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $quoteItem->addColumn('additional_data', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $quoteItem->addColumn('free_shipping', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $quoteItem->addColumn('is_qty_decimal', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $quoteItem->addColumn('no_discount', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $quoteItem->addColumn('weight', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteItem->addColumn('qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteItem->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteItem->addColumn('base_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteItem->addColumn('custom_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteItem->addColumn('discount_percent', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteItem->addColumn('discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteItem->addColumn('base_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteItem->addColumn('tax_percent', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteItem->addColumn('tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteItem->addColumn('base_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteItem->addColumn('row_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteItem->addColumn('base_row_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteItem->addColumn('row_total_with_discount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteItem->addColumn('row_weight', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteItem->addColumn('product_type', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteItem->addColumn('base_tax_before_discount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteItem->addColumn('tax_before_discount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteItem->addColumn('original_custom_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteItem->addColumn('redirect_url', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteItem->addColumn('base_cost', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteItem->addColumn('price_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteItem->addColumn('base_price_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteItem->addColumn('row_total_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteItem->addColumn('base_row_total_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteItem->addColumn('hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteItem->addColumn('base_hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteItem->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('item_id')->create(),
    );
    $quoteItem->addIndex(['parent_item_id'], 'idx_sales_flat_quote_item_parent_item_id');
    $quoteItem->addIndex(['product_id'], 'idx_sales_flat_quote_item_product_id');
    $quoteItem->addIndex(['quote_id'], 'idx_sales_flat_quote_item_quote_id');
    $quoteItem->addIndex(['store_id'], 'idx_sales_flat_quote_item_store_id');
    $quoteItem->addForeignKeyConstraint('sales_flat_quote_item', ['parent_item_id'], ['item_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_quote_item_parent');
    $quoteItem->addForeignKeyConstraint('sales_flat_quote', ['quote_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_quote_item_quote');
    $quoteItem->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_sales_flat_quote_item_store');
    $quoteItem->setComment('Sales Flat Quote Item');

    // sales_flat_quote_address_item
    // upgrade-1.6.0.11-1.6.0.12: created_at/updated_at default CURRENT_TIMESTAMP (MySQL)
    $quoteAddressItem = $schema->createTable('sales_flat_quote_address_item');
    $quoteAddressItem->addColumn('address_item_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $quoteAddressItem->addColumn('parent_item_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $quoteAddressItem->addColumn('quote_address_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $quoteAddressItem->addColumn('quote_item_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $quoteAddressItem->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $quoteAddressItem->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $quoteAddressItem->addColumn('applied_rule_ids', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $quoteAddressItem->addColumn('additional_data', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $quoteAddressItem->addColumn('weight', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteAddressItem->addColumn('qty', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddressItem->addColumn('discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteAddressItem->addColumn('tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteAddressItem->addColumn('row_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddressItem->addColumn('base_row_total', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteAddressItem->addColumn('row_total_with_discount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteAddressItem->addColumn('base_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteAddressItem->addColumn('base_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteAddressItem->addColumn('row_weight', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false, 'default' => '0.0000']);
    $quoteAddressItem->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $quoteAddressItem->addColumn('super_product_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $quoteAddressItem->addColumn('parent_product_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $quoteAddressItem->addColumn('sku', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddressItem->addColumn('image', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddressItem->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteAddressItem->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $quoteAddressItem->addColumn('free_shipping', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $quoteAddressItem->addColumn('is_qty_decimal', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $quoteAddressItem->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddressItem->addColumn('discount_percent', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddressItem->addColumn('no_discount', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $quoteAddressItem->addColumn('tax_percent', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddressItem->addColumn('base_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddressItem->addColumn('base_cost', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddressItem->addColumn('price_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddressItem->addColumn('base_price_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddressItem->addColumn('row_total_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddressItem->addColumn('base_row_total_incl_tax', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddressItem->addColumn('hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddressItem->addColumn('base_hidden_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $quoteAddressItem->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('address_item_id')->create(),
    );
    $quoteAddressItem->addIndex(['quote_address_id'], 'idx_sales_flat_quote_address_item_quote_address_id');
    $quoteAddressItem->addIndex(['parent_item_id'], 'idx_sales_flat_quote_address_item_parent_item_id');
    $quoteAddressItem->addIndex(['quote_item_id'], 'idx_sales_flat_quote_address_item_quote_item_id');
    $quoteAddressItem->addForeignKeyConstraint('sales_flat_quote_address', ['quote_address_id'], ['address_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_quote_address_item_quote_address');
    $quoteAddressItem->addForeignKeyConstraint('sales_flat_quote_address_item', ['parent_item_id'], ['address_item_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_quote_address_item_parent');
    $quoteAddressItem->addForeignKeyConstraint('sales_flat_quote_item', ['quote_item_id'], ['item_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_quote_address_item_quote_item');
    $quoteAddressItem->setComment('Sales Flat Quote Address Item');

    // sales_flat_quote_item_option
    $quoteItemOption = $schema->createTable('sales_flat_quote_item_option');
    $quoteItemOption->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $quoteItemOption->addColumn('item_id', Types::INTEGER, ['unsigned' => true]);
    $quoteItemOption->addColumn('product_id', Types::INTEGER, ['unsigned' => true]);
    $quoteItemOption->addColumn('code', Types::STRING, ['length' => 255]);
    $quoteItemOption->addColumn('value', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $quoteItemOption->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_id')->create(),
    );
    $quoteItemOption->addIndex(['item_id'], 'idx_sales_flat_quote_item_option_item_id');
    $quoteItemOption->addForeignKeyConstraint('sales_flat_quote_item', ['item_id'], ['item_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_quote_item_option_item');
    $quoteItemOption->setComment('Sales Flat Quote Item Option');

    // sales_flat_quote_payment
    // upgrade-1.6.0.11-1.6.0.12: created_at/updated_at default CURRENT_TIMESTAMP (MySQL)
    // Note: Maho_Paypal grafts paypal_order_id column + index on this table.
    $quotePayment = $schema->createTable('sales_flat_quote_payment');
    $quotePayment->addColumn('payment_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $quotePayment->addColumn('quote_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $quotePayment->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $quotePayment->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $quotePayment->addColumn('method', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quotePayment->addColumn('cc_type', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quotePayment->addColumn('cc_number_enc', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quotePayment->addColumn('cc_last4', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quotePayment->addColumn('cc_cid_enc', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quotePayment->addColumn('cc_owner', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quotePayment->addColumn('cc_exp_month', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $quotePayment->addColumn('cc_exp_year', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $quotePayment->addColumn('cc_ss_owner', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quotePayment->addColumn('cc_ss_start_month', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $quotePayment->addColumn('cc_ss_start_year', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $quotePayment->addColumn('po_number', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quotePayment->addColumn('additional_data', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $quotePayment->addColumn('cc_ss_issue', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quotePayment->addColumn('additional_information', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $quotePayment->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('payment_id')->create(),
    );
    $quotePayment->addIndex(['quote_id'], 'idx_sales_flat_quote_payment_quote_id');
    $quotePayment->addForeignKeyConstraint('sales_flat_quote', ['quote_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_quote_payment_quote');
    $quotePayment->setComment('Sales Flat Quote Payment');

    // sales_flat_quote_shipping_rate (alias: quote_address_shipping_rate)
    // upgrade-1.6.0.11-1.6.0.12: created_at/updated_at default CURRENT_TIMESTAMP (MySQL)
    $quoteShippingRate = $schema->createTable('sales_flat_quote_shipping_rate');
    $quoteShippingRate->addColumn('rate_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $quoteShippingRate->addColumn('address_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $quoteShippingRate->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $quoteShippingRate->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $quoteShippingRate->addColumn('carrier', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteShippingRate->addColumn('carrier_title', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteShippingRate->addColumn('code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteShippingRate->addColumn('method', Types::STRING, ['length' => 255, 'notnull' => false]);
    $quoteShippingRate->addColumn('method_description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $quoteShippingRate->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $quoteShippingRate->addColumn('error_message', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $quoteShippingRate->addColumn('method_title', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $quoteShippingRate->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rate_id')->create(),
    );
    $quoteShippingRate->addIndex(['address_id'], 'idx_sales_flat_quote_shipping_rate_address_id');
    $quoteShippingRate->addForeignKeyConstraint('sales_flat_quote_address', ['address_id'], ['address_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_flat_quote_shipping_rate_address');
    $quoteShippingRate->setComment('Sales Flat Quote Shipping Rate');

    // Two structurally identical invoiced aggregation tables.
    foreach (['sales_invoiced_aggregated', 'sales_invoiced_aggregated_order'] as $tableName) {
        $aggr = $schema->createTable($tableName);
        $aggr->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
        $aggr->addColumn('period', Types::DATE_MUTABLE, ['notnull' => false]);
        $aggr->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
        $aggr->addColumn('order_status', Types::STRING, ['length' => 50, 'default' => '']);
        $aggr->addColumn('orders_count', Types::INTEGER, ['default' => 0]);
        $aggr->addColumn('orders_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $aggr->addColumn('invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $aggr->addColumn('invoiced_captured', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $aggr->addColumn('invoiced_not_captured', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $aggr->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
        );
        $short = str_replace('sales_invoiced_aggregated', 'sales_inv_aggr', $tableName);
        $aggr->addUniqueIndex(['period', 'store_id', 'order_status'], "unq_{$short}_period_store_status");
        $aggr->addIndex(['store_id'], "idx_{$short}_store_id");
        $aggr->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], "fk_{$short}_store");
        $aggr->setComment('Sales Invoiced Aggregated');
    }

    // Two structurally identical order aggregation tables.
    // sales_order_aggregated_updated added by upgrade-1.6.0.3-1.6.0.4.
    foreach (['sales_order_aggregated_created', 'sales_order_aggregated_updated'] as $tableName) {
        $aggr = $schema->createTable($tableName);
        $aggr->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
        $aggr->addColumn('period', Types::DATE_MUTABLE, ['notnull' => false]);
        $aggr->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
        $aggr->addColumn('order_status', Types::STRING, ['length' => 50, 'default' => '']);
        $aggr->addColumn('orders_count', Types::INTEGER, ['default' => 0]);
        $aggr->addColumn('total_qty_ordered', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_qty_invoiced', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_income_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_revenue_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_profit_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_invoiced_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_canceled_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_paid_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_refunded_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_tax_amount_actual', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_shipping_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_shipping_amount_actual', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_discount_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('total_discount_amount_actual', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
        );
        $short = str_replace('sales_order_aggregated_', 'sales_order_aggr_', $tableName);
        $aggr->addUniqueIndex(['period', 'store_id', 'order_status'], "unq_{$short}_period_store_status");
        $aggr->addIndex(['store_id'], "idx_{$short}_store_id");
        $aggr->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], "fk_{$short}_store");
        $aggr->setComment('Sales Order Aggregated');
    }

    // sales_payment_transaction
    $paymentTransaction = $schema->createTable('sales_payment_transaction');
    $paymentTransaction->addColumn('transaction_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $paymentTransaction->addColumn('parent_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $paymentTransaction->addColumn('order_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $paymentTransaction->addColumn('payment_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $paymentTransaction->addColumn('txn_id', Types::STRING, ['length' => 100, 'notnull' => false]);
    $paymentTransaction->addColumn('parent_txn_id', Types::STRING, ['length' => 100, 'notnull' => false]);
    $paymentTransaction->addColumn('txn_type', Types::STRING, ['length' => 15, 'notnull' => false]);
    $paymentTransaction->addColumn('is_closed', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $paymentTransaction->addColumn('additional_information', Types::BLOB, ['length' => 65535, 'notnull' => false]);
    $paymentTransaction->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $paymentTransaction->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('transaction_id')->create(),
    );
    $paymentTransaction->addUniqueIndex(['order_id', 'payment_id', 'txn_id'], 'unq_sales_payment_transaction_order_payment_txn');
    $paymentTransaction->addIndex(['order_id'], 'idx_sales_payment_transaction_order_id');
    $paymentTransaction->addIndex(['parent_id'], 'idx_sales_payment_transaction_parent_id');
    $paymentTransaction->addIndex(['payment_id'], 'idx_sales_payment_transaction_payment_id');
    $paymentTransaction->addForeignKeyConstraint('sales_flat_order', ['order_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_payment_transaction_order');
    $paymentTransaction->addForeignKeyConstraint('sales_payment_transaction', ['parent_id'], ['transaction_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_payment_transaction_parent');
    $paymentTransaction->addForeignKeyConstraint('sales_flat_order_payment', ['payment_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_payment_transaction_payment');
    $paymentTransaction->setComment('Sales Payment Transaction');

    // Two structurally identical refunded aggregation tables.
    foreach (['sales_refunded_aggregated', 'sales_refunded_aggregated_order'] as $tableName) {
        $aggr = $schema->createTable($tableName);
        $aggr->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
        $aggr->addColumn('period', Types::DATE_MUTABLE, ['notnull' => false]);
        $aggr->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
        // sales_refunded_aggregated has default ''; sales_refunded_aggregated_order is nullable. Match
        // legacy install difference (order variant uses default null on order_status).
        if ($tableName === 'sales_refunded_aggregated_order') {
            $aggr->addColumn('order_status', Types::STRING, ['length' => 50, 'notnull' => false]);
        } else {
            $aggr->addColumn('order_status', Types::STRING, ['length' => 50, 'default' => '']);
        }
        $aggr->addColumn('orders_count', Types::INTEGER, ['default' => 0]);
        $aggr->addColumn('refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $aggr->addColumn('online_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $aggr->addColumn('offline_refunded', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $aggr->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
        );
        $short = str_replace('sales_refunded_aggregated', 'sales_ref_aggr', $tableName);
        $aggr->addUniqueIndex(['period', 'store_id', 'order_status'], "unq_{$short}_period_store_status");
        $aggr->addIndex(['store_id'], "idx_{$short}_store_id");
        $aggr->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], "fk_{$short}_store");
        $aggr->setComment('Sales Refunded Aggregated');
    }

    // Two structurally identical shipping aggregation tables.
    foreach (['sales_shipping_aggregated', 'sales_shipping_aggregated_order'] as $tableName) {
        $aggr = $schema->createTable($tableName);
        $aggr->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
        $aggr->addColumn('period', Types::DATE_MUTABLE, ['notnull' => false]);
        $aggr->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
        $aggr->addColumn('order_status', Types::STRING, ['length' => 50, 'notnull' => false]);
        $aggr->addColumn('shipping_description', Types::STRING, ['length' => 255, 'notnull' => false]);
        $aggr->addColumn('orders_count', Types::INTEGER, ['default' => 0]);
        $aggr->addColumn('total_shipping', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $aggr->addColumn('total_shipping_actual', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        $aggr->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
        );
        $short = str_replace('sales_shipping_aggregated', 'sales_ship_aggr', $tableName);
        $aggr->addUniqueIndex(['period', 'store_id', 'order_status', 'shipping_description'], "unq_{$short}_period_store_status_desc");
        $aggr->addIndex(['store_id'], "idx_{$short}_store_id");
        $aggr->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], "fk_{$short}_store");
        $aggr->setComment('Sales Shipping Aggregated');
    }

    // Three structurally identical bestsellers aggregation tables.
    // upgrade-1.6.0.9-1.6.0.10: added product_type_id column on all three.
    // FK to catalog_product_entity is deferred until Mage_Catalog is converted to declarative schema.
    foreach (['daily', 'monthly', 'yearly'] as $period) {
        $tableName = "sales_bestsellers_aggregated_{$period}";
        $aggr = $schema->createTable($tableName);
        $aggr->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
        $aggr->addColumn('period', Types::DATE_MUTABLE, ['notnull' => false]);
        $aggr->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
        $aggr->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
        $aggr->addColumn('product_type_id', Types::STRING, ['length' => 32, 'default' => 'simple']);
        $aggr->addColumn('product_name', Types::STRING, ['length' => 255, 'notnull' => false]);
        $aggr->addColumn('product_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('qty_ordered', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('rating_pos', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
        $aggr->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
        );
        $short = "sales_bs_aggr_{$period}";
        $aggr->addUniqueIndex(['period', 'store_id', 'product_id'], "unq_{$short}_period_store_product");
        $aggr->addIndex(['store_id'], "idx_{$short}_store_id");
        $aggr->addIndex(['product_id'], "idx_{$short}_product_id");
        $aggr->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], "fk_{$short}_store");
        $aggr->setComment('Sales Bestsellers Aggregated ' . ucfirst($period));
    }

    // sales_billing_agreement
    // upgrade-1.6.0.11-1.6.0.12: created_at default CURRENT_TIMESTAMP (MySQL)
    $billingAgreement = $schema->createTable('sales_billing_agreement');
    $billingAgreement->addColumn('agreement_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $billingAgreement->addColumn('customer_id', Types::INTEGER, ['unsigned' => true]);
    $billingAgreement->addColumn('method_code', Types::STRING, ['length' => 32]);
    $billingAgreement->addColumn('reference_id', Types::STRING, ['length' => 32]);
    $billingAgreement->addColumn('status', Types::STRING, ['length' => 20]);
    $billingAgreement->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $billingAgreement->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $billingAgreement->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $billingAgreement->addColumn('agreement_label', Types::STRING, ['length' => 255, 'notnull' => false]);
    $billingAgreement->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('agreement_id')->create(),
    );
    $billingAgreement->addIndex(['customer_id'], 'idx_sales_billing_agreement_customer_id');
    $billingAgreement->addIndex(['store_id'], 'idx_sales_billing_agreement_store_id');
    $billingAgreement->addForeignKeyConstraint('customer_entity', ['customer_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_billing_agreement_customer');
    $billingAgreement->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_sales_billing_agreement_store');
    $billingAgreement->setComment('Sales Billing Agreement');

    // sales_billing_agreement_order
    $billingAgreementOrder = $schema->createTable('sales_billing_agreement_order');
    $billingAgreementOrder->addColumn('agreement_id', Types::INTEGER, ['unsigned' => true]);
    $billingAgreementOrder->addColumn('order_id', Types::INTEGER, ['unsigned' => true]);
    $billingAgreementOrder->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('agreement_id', 'order_id')->create(),
    );
    $billingAgreementOrder->addIndex(['order_id'], 'idx_sales_billing_agreement_order_order_id');
    $billingAgreementOrder->addForeignKeyConstraint('sales_billing_agreement', ['agreement_id'], ['agreement_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_billing_agreement_order_agreement');
    $billingAgreementOrder->addForeignKeyConstraint('sales_flat_order', ['order_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_billing_agreement_order_order');
    $billingAgreementOrder->setComment('Sales Billing Agreement Order');

    // sales_recurring_profile
    // upgrade-1.6.0.11-1.6.0.12: created_at + start_datetime default CURRENT_TIMESTAMP (MySQL)
    $recurringProfile = $schema->createTable('sales_recurring_profile');
    $recurringProfile->addColumn('profile_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $recurringProfile->addColumn('state', Types::STRING, ['length' => 20]);
    $recurringProfile->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $recurringProfile->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $recurringProfile->addColumn('method_code', Types::STRING, ['length' => 32]);
    $recurringProfile->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $recurringProfile->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $recurringProfile->addColumn('reference_id', Types::STRING, ['length' => 32, 'notnull' => false]);
    $recurringProfile->addColumn('subscriber_name', Types::STRING, ['length' => 150, 'notnull' => false]);
    $recurringProfile->addColumn('start_datetime', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $recurringProfile->addColumn('internal_reference_id', Types::STRING, ['length' => 42]);
    $recurringProfile->addColumn('schedule_description', Types::STRING, ['length' => 255]);
    $recurringProfile->addColumn('suspension_threshold', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $recurringProfile->addColumn('bill_failed_later', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $recurringProfile->addColumn('period_unit', Types::STRING, ['length' => 20]);
    $recurringProfile->addColumn('period_frequency', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $recurringProfile->addColumn('period_max_cycles', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $recurringProfile->addColumn('billing_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $recurringProfile->addColumn('trial_period_unit', Types::STRING, ['length' => 20, 'notnull' => false]);
    $recurringProfile->addColumn('trial_period_frequency', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $recurringProfile->addColumn('trial_period_max_cycles', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $recurringProfile->addColumn('trial_billing_amount', Types::STRING, ['length' => 255, 'notnull' => false]);
    $recurringProfile->addColumn('currency_code', Types::STRING, ['length' => 3]);
    $recurringProfile->addColumn('shipping_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $recurringProfile->addColumn('tax_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $recurringProfile->addColumn('init_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $recurringProfile->addColumn('init_may_fail', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $recurringProfile->addColumn('order_info', Types::TEXT, ['length' => 65535]);
    $recurringProfile->addColumn('order_item_info', Types::TEXT, ['length' => 65535]);
    $recurringProfile->addColumn('billing_address_info', Types::TEXT, ['length' => 65535]);
    $recurringProfile->addColumn('shipping_address_info', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $recurringProfile->addColumn('profile_vendor_info', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $recurringProfile->addColumn('additional_info', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $recurringProfile->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('profile_id')->create(),
    );
    $recurringProfile->addUniqueIndex(['internal_reference_id'], 'unq_sales_recurring_profile_internal_reference_id');
    $recurringProfile->addIndex(['customer_id'], 'idx_sales_recurring_profile_customer_id');
    $recurringProfile->addIndex(['store_id'], 'idx_sales_recurring_profile_store_id');
    $recurringProfile->addForeignKeyConstraint('customer_entity', ['customer_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_sales_recurring_profile_customer');
    $recurringProfile->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_sales_recurring_profile_store');
    $recurringProfile->setComment('Sales Recurring Profile');

    // sales_recurring_profile_order
    $recurringProfileOrder = $schema->createTable('sales_recurring_profile_order');
    $recurringProfileOrder->addColumn('link_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $recurringProfileOrder->addColumn('profile_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $recurringProfileOrder->addColumn('order_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $recurringProfileOrder->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('link_id')->create(),
    );
    $recurringProfileOrder->addUniqueIndex(['profile_id', 'order_id'], 'unq_sales_recurring_profile_order_profile_order');
    $recurringProfileOrder->addIndex(['order_id'], 'idx_sales_recurring_profile_order_order_id');
    $recurringProfileOrder->addForeignKeyConstraint('sales_flat_order', ['order_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_recurring_profile_order_order');
    $recurringProfileOrder->addForeignKeyConstraint('sales_recurring_profile', ['profile_id'], ['profile_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_recurring_profile_order_profile');
    $recurringProfileOrder->setComment('Sales Recurring Profile Order');

    // sales_order_tax
    // Note: sales_order_tax_item is owned by Mage_Tax (already declarative).
    $orderTax = $schema->createTable('sales_order_tax');
    $orderTax->addColumn('tax_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $orderTax->addColumn('order_id', Types::INTEGER, ['unsigned' => true]);
    $orderTax->addColumn('code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderTax->addColumn('title', Types::STRING, ['length' => 255, 'notnull' => false]);
    $orderTax->addColumn('percent', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderTax->addColumn('amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderTax->addColumn('priority', Types::INTEGER);
    $orderTax->addColumn('position', Types::INTEGER);
    $orderTax->addColumn('base_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderTax->addColumn('process', Types::SMALLINT);
    $orderTax->addColumn('base_real_amount', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
    $orderTax->addColumn('hidden', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $orderTax->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tax_id')->create(),
    );
    $orderTax->addIndex(['order_id', 'priority', 'position'], 'idx_sales_order_tax_order_id_priority_position');
    $orderTax->setComment('Sales Order Tax');

    // sales_order_status
    $orderStatus = $schema->createTable('sales_order_status');
    $orderStatus->addColumn('status', Types::STRING, ['length' => 32]);
    $orderStatus->addColumn('label', Types::STRING, ['length' => 128]);
    $orderStatus->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('status')->create(),
    );
    $orderStatus->setComment('Sales Order Status');

    // sales_order_status_state
    $orderStatusState = $schema->createTable('sales_order_status_state');
    $orderStatusState->addColumn('status', Types::STRING, ['length' => 32]);
    $orderStatusState->addColumn('state', Types::STRING, ['length' => 32]);
    $orderStatusState->addColumn('is_default', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $orderStatusState->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('status', 'state')->create(),
    );
    $orderStatusState->addForeignKeyConstraint('sales_order_status', ['status'], ['status'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_order_status_state_status');
    $orderStatusState->setComment('Sales Order Status State');

    // sales_order_status_label
    $orderStatusLabel = $schema->createTable('sales_order_status_label');
    $orderStatusLabel->addColumn('status', Types::STRING, ['length' => 32]);
    $orderStatusLabel->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $orderStatusLabel->addColumn('label', Types::STRING, ['length' => 128]);
    $orderStatusLabel->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('status', 'store_id')->create(),
    );
    $orderStatusLabel->addIndex(['store_id'], 'idx_sales_order_status_label_store_id');
    $orderStatusLabel->addForeignKeyConstraint('sales_order_status', ['status'], ['status'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_order_status_label_status');
    $orderStatusLabel->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_sales_order_status_label_store');
    $orderStatusLabel->setComment('Sales Order Status Label');
};
