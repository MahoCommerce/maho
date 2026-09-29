<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('customer_entity')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_set_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('email', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('group_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('increment_id', Types::STRING, length: 50, notNull: false))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('disable_auto_group_change', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('twofa_enabled', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('twofa_secret', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type_id'))
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('email', 'website_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Customer Entity')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('customer_address_entity')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_set_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('increment_id', Types::STRING, length: 50, notNull: false))
            ->addColumn(Schema::column('parent_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, unsigned: true, default: 1))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('parent_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('parent_id')
                    ->setUnquotedReferencedTableName('customer_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Customer Address Entity')
            ->create(),
    );

    // 10 structurally near-identical EAV value tables (5 typed per parent entity).
    // Each shares the FK set (parent entity, eav_attribute, eav_entity_type) and
    // the same index shape, with only the `value` column type differing per backend.
    $valueTables = [
        'customer_address_entity_datetime' => [
            'parent' => 'customer_address_entity',
            'value' => Schema::column('value', Types::DATETIME_MUTABLE, notNull: false),
            'hasValueIndex' => true,
        ],
        'customer_address_entity_decimal' => [
            'parent' => 'customer_address_entity',
            'value' => Schema::column('value', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'),
            'hasValueIndex' => true,
        ],
        'customer_address_entity_int' => [
            'parent' => 'customer_address_entity',
            'value' => Schema::column('value', Types::INTEGER, default: 0),
            'hasValueIndex' => true,
        ],
        'customer_address_entity_text' => [
            'parent' => 'customer_address_entity',
            'value' => Schema::column('value', Types::TEXT, length: 65535),
            'hasValueIndex' => false,
        ],
        'customer_address_entity_varchar' => [
            'parent' => 'customer_address_entity',
            'value' => Schema::column('value', Types::STRING, length: 255, notNull: false),
            'hasValueIndex' => true,
        ],
        'customer_entity_datetime' => [
            'parent' => 'customer_entity',
            'value' => Schema::column('value', Types::DATETIME_MUTABLE, notNull: false),
            'hasValueIndex' => true,
        ],
        'customer_entity_decimal' => [
            'parent' => 'customer_entity',
            'value' => Schema::column('value', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'),
            'hasValueIndex' => true,
        ],
        'customer_entity_int' => [
            'parent' => 'customer_entity',
            'value' => Schema::column('value', Types::INTEGER, default: 0),
            'hasValueIndex' => true,
        ],
        'customer_entity_text' => [
            'parent' => 'customer_entity',
            'value' => Schema::column('value', Types::TEXT, length: 65535),
            'hasValueIndex' => false,
        ],
        'customer_entity_varchar' => [
            'parent' => 'customer_entity',
            'value' => Schema::column('value', Types::STRING, length: 255, notNull: false),
            'hasValueIndex' => true,
        ],
    ];
    foreach ($valueTables as $tableName => $spec) {
        $t = Table::editor()
            ->setUnquotedName($tableName)
            ->addColumn(Schema::column('value_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn($spec['value'])
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('entity_id', 'attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'));
        if ($spec['hasValueIndex']) {
            $t
                ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id', 'attribute_id', 'value'));
        }
        $t
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('attribute_id')
                    ->setUnquotedReferencedTableName('eav_attribute')
                    ->setUnquotedReferencedColumnNames('attribute_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName($spec['parent'])
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_type_id')
                    ->setUnquotedReferencedTableName('eav_entity_type')
                    ->setUnquotedReferencedColumnNames('entity_type_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment(ucwords(str_replace('_', ' ', $tableName)));
        $schema->addTable($t->create());
    }

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('customer_group')
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('customer_group_code', Types::STRING, length: 32))
            ->addColumn(Schema::column('tax_class_id', Types::INTEGER, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('customer_group_id')
                    ->create(),
            )
            ->setComment('Customer Group')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('customer_eav_attribute')
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('is_visible', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('input_filter', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('multiline_count', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('validate_rules', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('is_system', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('sort_order', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('data_model', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_id')->create())
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('attribute_id')
                    ->setUnquotedReferencedTableName('eav_attribute')
                    ->setUnquotedReferencedColumnNames('attribute_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Customer Eav Attribute')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('customer_form_attribute')
            ->addColumn(Schema::column('form_code', Types::STRING, length: 32))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('form_code', 'attribute_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('attribute_id')
                    ->setUnquotedReferencedTableName('eav_attribute')
                    ->setUnquotedReferencedColumnNames('attribute_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Customer Form Attribute')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('customer_eav_attribute_website')
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('is_visible', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('is_required', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('default_value', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('multiline_count', Types::SMALLINT, unsigned: true, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('attribute_id', 'website_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('attribute_id')
                    ->setUnquotedReferencedTableName('eav_attribute')
                    ->setUnquotedReferencedColumnNames('attribute_id')
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
            ->setComment('Customer Eav Attribute Website')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('customer_flowpassword')
            ->addColumn(Schema::column('flowpassword_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('ip', Types::STRING, length: 50))
            ->addColumn(Schema::column('email', Types::STRING, length: 255))
            // Model _beforeSave always populates requested_date with formatDateForDb('now');
            // no DB-level default needed.
            ->addColumn(Schema::column('requested_date', Types::STRING, length: 255))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('flowpassword_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('email'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('ip'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('requested_date'))
            ->setComment('Customer flow password')
            ->create(),
    );
};
