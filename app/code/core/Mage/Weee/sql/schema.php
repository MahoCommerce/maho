<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $tax = $schema->createTable('weee_tax');
    $tax->addColumn('value_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $tax->addColumn('website_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $tax->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $tax->addColumn('country', Types::STRING, ['length' => 2, 'notnull' => false]);
    $tax->addColumn('value', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $tax->addColumn('state', Types::STRING, ['length' => 255, 'default' => '*']);
    $tax->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true]);
    $tax->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true]);
    $tax->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $tax->addIndex(['website_id']);
    $tax->addIndex(['entity_id']);
    $tax->addIndex(['country']);
    $tax->addIndex(['attribute_id']);
    $tax->addForeignKeyConstraint('directory_country', ['country'], ['country_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $tax->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $tax->addForeignKeyConstraint('catalog_product_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $tax->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $tax->setComment('Weee Tax');

    $discount = $schema->createTable('weee_discount');
    $discount->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $discount->addColumn('website_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $discount->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $discount->addColumn('value', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $discount->addIndex(['website_id']);
    $discount->addIndex(['entity_id']);
    $discount->addIndex(['customer_group_id']);
    $discount->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $discount->addForeignKeyConstraint('customer_group', ['customer_group_id'], ['customer_group_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $discount->addForeignKeyConstraint('catalog_product_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $discount->setComment('Weee Discount');

    // Graft the WEEE tax columns onto the flat sales/quote item tables owned by
    // Mage_Sales (depends_on guarantees those tables already exist). The legacy
    // install added these via addAttribute() on the flat sales entities;
    // declaring them keeps fresh installs complete and lets the migration
    // recognise the existing columns instead of dropping them.
    $decimalColumns = [
        'weee_tax_applied_amount',
        'weee_tax_applied_row_amount',
        'base_weee_tax_applied_amount',
        'base_weee_tax_applied_row_amnt',
        'weee_tax_disposition',
        'weee_tax_row_disposition',
        'base_weee_tax_disposition',
        'base_weee_tax_row_disposition',
    ];
    foreach ([
        'sales_flat_quote_item',
        'sales_flat_order_item',
        'sales_flat_invoice_item',
        'sales_flat_creditmemo_item',
    ] as $tableName) {
        $table = $schema->getTable($tableName);
        $table->addColumn('weee_tax_applied', Types::TEXT, ['length' => 65535, 'notnull' => false]);
        foreach ($decimalColumns as $column) {
            $table->addColumn($column, Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'notnull' => false]);
        }
    }
};
