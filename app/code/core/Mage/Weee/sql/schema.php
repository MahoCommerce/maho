<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('weee_tax')
            ->addColumn(Schema::column('value_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('country', Types::STRING, length: 2, notNull: false))
            ->addColumn(Schema::column('value', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addColumn(Schema::column('state', Types::STRING, length: 255, default: '*'))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('country'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('country')
                    ->setUnquotedReferencedTableName('directory_country')
                    ->setUnquotedReferencedColumnNames('country_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('attribute_id')
                    ->setUnquotedReferencedTableName('eav_attribute')
                    ->setUnquotedReferencedColumnNames('attribute_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Weee Tax')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('weee_discount')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('value', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_group_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_group_id')
                    ->setUnquotedReferencedTableName('customer_group')
                    ->setUnquotedReferencedColumnNames('customer_group_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName('catalog_product_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Weee Discount')
            ->create(),
    );

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
        $schema->modifyTableByUnquotedName($tableName, static function (TableEditor $table) use ($decimalColumns): void {
            $table->addColumn(Schema::column('weee_tax_applied', Types::TEXT, length: 65535, notNull: false));
            foreach ($decimalColumns as $column) {
                $table->addColumn(Schema::column($column, Types::DECIMAL, precision: 12, scale: 4, notNull: false));
            }
        });
    }
};
