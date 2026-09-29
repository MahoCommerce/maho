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

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('customer_entity')
            ->addColumn(Column::editor()->setUnquotedName('entity_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_set_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('website_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('email')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('group_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('increment_id')->setTypeName(Types::STRING)->setLength(50)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('is_active')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(1)->create())
            ->addColumn(Column::editor()->setUnquotedName('disable_auto_group_change')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('twofa_enabled')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('twofa_secret')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
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
            ->addColumn(Column::editor()->setUnquotedName('entity_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_set_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('increment_id')->setTypeName(Types::STRING)->setLength(50)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('parent_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('is_active')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(1)->create())
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
        'customer_address_entity_datetime' => ['parent' => 'customer_address_entity', 'value' => Column::editor()->setUnquotedName('value')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create(), 'hasValueIndex' => true],
        'customer_address_entity_decimal'  => ['parent' => 'customer_address_entity', 'value' => Column::editor()->setUnquotedName('value')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create(), 'hasValueIndex' => true],
        'customer_address_entity_int'      => ['parent' => 'customer_address_entity', 'value' => Column::editor()->setUnquotedName('value')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create(), 'hasValueIndex' => true],
        'customer_address_entity_text'     => ['parent' => 'customer_address_entity', 'value' => Column::editor()->setUnquotedName('value')->setTypeName(Types::TEXT)->setLength(65535)->create(), 'hasValueIndex' => false],
        'customer_address_entity_varchar'  => ['parent' => 'customer_address_entity', 'value' => Column::editor()->setUnquotedName('value')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(), 'hasValueIndex' => true],
        'customer_entity_datetime'         => ['parent' => 'customer_entity',         'value' => Column::editor()->setUnquotedName('value')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create(), 'hasValueIndex' => true],
        'customer_entity_decimal'          => ['parent' => 'customer_entity',         'value' => Column::editor()->setUnquotedName('value')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create(), 'hasValueIndex' => true],
        'customer_entity_int'              => ['parent' => 'customer_entity',         'value' => Column::editor()->setUnquotedName('value')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create(), 'hasValueIndex' => true],
        'customer_entity_text'             => ['parent' => 'customer_entity',         'value' => Column::editor()->setUnquotedName('value')->setTypeName(Types::TEXT)->setLength(65535)->create(), 'hasValueIndex' => false],
        'customer_entity_varchar'          => ['parent' => 'customer_entity',         'value' => Column::editor()->setUnquotedName('value')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(), 'hasValueIndex' => true],
    ];
    foreach ($valueTables as $tableName => $spec) {
        $t = Table::editor()
            ->setUnquotedName($tableName)
            ->addColumn(Column::editor()->setUnquotedName('value_id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
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
            ->addColumn(Column::editor()->setUnquotedName('customer_group_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('customer_group_code')->setTypeName(Types::STRING)->setLength(32)->create())
            ->addColumn(Column::editor()->setUnquotedName('tax_class_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('customer_group_id')->create())
            ->setComment('Customer Group')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('customer_eav_attribute')
            ->addColumn(Column::editor()->setUnquotedName('attribute_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_visible')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(1)->create())
            ->addColumn(Column::editor()->setUnquotedName('input_filter')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('multiline_count')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(1)->create())
            ->addColumn(Column::editor()->setUnquotedName('validate_rules')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_system')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('sort_order')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('data_model')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
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
            ->addColumn(Column::editor()->setUnquotedName('form_code')->setTypeName(Types::STRING)->setLength(32)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('form_code', 'attribute_id')->create())
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
            ->addColumn(Column::editor()->setUnquotedName('attribute_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('website_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_visible')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_required')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('default_value')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('multiline_count')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_id', 'website_id')->create())
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
            ->addColumn(Column::editor()->setUnquotedName('flowpassword_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('ip')->setTypeName(Types::STRING)->setLength(50)->create())
            ->addColumn(Column::editor()->setUnquotedName('email')->setTypeName(Types::STRING)->setLength(255)->create())
            // Model _beforeSave always populates requested_date with formatDateForDb('now');
            // no DB-level default needed.
            ->addColumn(Column::editor()->setUnquotedName('requested_date')->setTypeName(Types::STRING)->setLength(255)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('flowpassword_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('email'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('ip'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('requested_date'))
            ->setComment('Customer flow password')
            ->create(),
    );
};
