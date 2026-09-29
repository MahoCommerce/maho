<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
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
            ->setUnquotedName('eav_entity_type')
            ->addColumn(Column::editor()->setUnquotedName('entity_type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_type_code')->setTypeName(Types::STRING)->setLength(50)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_model')->setTypeName(Types::STRING)->setLength(255)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_model')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_table')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('value_table_prefix')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_id_field')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_data_sharing')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(1)->create())
            ->addColumn(Column::editor()->setUnquotedName('data_sharing_key')->setTypeName(Types::STRING)->setLength(100)->setNotNull(false)->setDefaultValue('default')->create())
            ->addColumn(Column::editor()->setUnquotedName('default_attribute_set_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('increment_model')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->setDefaultValue('')->create())
            ->addColumn(Column::editor()->setUnquotedName('increment_per_store')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('increment_pad_length')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(8)->create())
            ->addColumn(Column::editor()->setUnquotedName('increment_pad_char')->setTypeName(Types::STRING)->setLength(1)->setDefaultValue('0')->create())
            ->addColumn(Column::editor()->setUnquotedName('additional_attribute_table')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->setDefaultValue('')->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_attribute_collection')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_type_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type_code'))
            ->setComment('Eav Entity Type')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_entity')
            ->addColumn(Column::editor()->setUnquotedName('entity_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_set_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('increment_id')->setTypeName(Types::STRING)->setLength(50)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('parent_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::DATETIME_MUTABLE)->setDefaultValue(new CurrentTimestamp())->create())
            ->addColumn(Column::editor()->setUnquotedName('is_active')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(1)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_type_id')
                    ->setUnquotedReferencedTableName('eav_entity_type')
                    ->setUnquotedReferencedColumnNames('entity_type_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Eav Entity')
            ->create(),
    );

    // Five structurally near-identical value tables keyed by backend_type.
    // Each shares the FK set (entity, entity_type, store) and the same index
    // shape, with only the `value` column type differing per backend.
    $valueTables = [
        'eav_entity_datetime' => ['value' => Column::editor()->setUnquotedName('value')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create(), 'hasValueIndex' => true],
        'eav_entity_decimal'  => ['value' => Column::editor()->setUnquotedName('value')->setTypeName(Types::DECIMAL)->setPrecision(12)->setScale(4)->setDefaultValue('0.0000')->create(), 'hasValueIndex' => true],
        'eav_entity_int'      => ['value' => Column::editor()->setUnquotedName('value')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create(), 'hasValueIndex' => true],
        'eav_entity_text'     => ['value' => Column::editor()->setUnquotedName('value')->setTypeName(Types::TEXT)->setLength(65535)->create(), 'hasValueIndex' => false],
        'eav_entity_varchar'  => ['value' => Column::editor()->setUnquotedName('value')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(), 'hasValueIndex' => true],
    ];
    foreach ($valueTables as $tableName => $spec) {
        $t = Table::editor()
            ->setUnquotedName($tableName)
            ->addColumn(Column::editor()->setUnquotedName('value_id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn($spec['value'])
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_id'));
        if ($spec['hasValueIndex']) {
            $t
                ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id', 'value'))
                ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type_id', 'value'));
        }
        $t
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_id')
                    ->setUnquotedReferencedTableName('eav_entity')
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
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Eav Entity Value Prefix');
        $schema->addTable($t->create());
    }

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_attribute')
            ->addColumn(Column::editor()->setUnquotedName('attribute_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_code')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_model')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('backend_model')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('backend_type')->setTypeName(Types::STRING)->setLength(8)->setDefaultValue('static')->create())
            ->addColumn(Column::editor()->setUnquotedName('backend_table')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('frontend_model')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('frontend_input')->setTypeName(Types::STRING)->setLength(50)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('frontend_label')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('frontend_class')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('source_model')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_required')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_user_defined')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('default_value')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_unique')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('note')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('entity_type_id', 'attribute_code'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_type_id')
                    ->setUnquotedReferencedTableName('eav_entity_type')
                    ->setUnquotedReferencedColumnNames('entity_type_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Eav Attribute')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_entity_store')
            ->addColumn(Column::editor()->setUnquotedName('entity_store_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('increment_prefix')->setTypeName(Types::STRING)->setLength(20)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('increment_last_id')->setTypeName(Types::STRING)->setLength(50)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_store_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_type_id')
                    ->setUnquotedReferencedTableName('eav_entity_type')
                    ->setUnquotedReferencedColumnNames('entity_type_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Eav Entity Store')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_attribute_set')
            ->addColumn(Column::editor()->setUnquotedName('attribute_set_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_set_name')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('sort_order')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_set_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('entity_type_id', 'attribute_set_name'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type_id', 'sort_order'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_type_id')
                    ->setUnquotedReferencedTableName('eav_entity_type')
                    ->setUnquotedReferencedColumnNames('entity_type_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Eav Attribute Set')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_attribute_group')
            ->addColumn(Column::editor()->setUnquotedName('attribute_group_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_set_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_group_name')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('sort_order')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('default_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_group_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('attribute_set_id', 'attribute_group_name'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_set_id', 'sort_order'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('attribute_set_id')
                    ->setUnquotedReferencedTableName('eav_attribute_set')
                    ->setUnquotedReferencedColumnNames('attribute_set_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Eav Attribute Group')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_entity_attribute')
            ->addColumn(Column::editor()->setUnquotedName('entity_attribute_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_set_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_group_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('sort_order')->setTypeName(Types::SMALLINT)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_attribute_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('attribute_set_id', 'attribute_id'))
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('attribute_group_id', 'attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_set_id', 'sort_order'))
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
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('attribute_group_id')
                    ->setUnquotedReferencedTableName('eav_attribute_group')
                    ->setUnquotedReferencedColumnNames('attribute_group_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Eav Entity Attributes')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_attribute_option')
            ->addColumn(Column::editor()->setUnquotedName('option_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('sort_order')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_id')->create())
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
            ->setComment('Eav Attribute Option')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_attribute_option_value')
            ->addColumn(Column::editor()->setUnquotedName('value_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('option_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('value')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('option_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('option_id')
                    ->setUnquotedReferencedTableName('eav_attribute_option')
                    ->setUnquotedReferencedColumnNames('option_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Eav Attribute Option Value')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_attribute_option_swatch')
            ->addColumn(Column::editor()->setUnquotedName('value_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('option_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('value')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('filename')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('option_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('option_id')
                    ->setUnquotedReferencedTableName('eav_attribute_option')
                    ->setUnquotedReferencedColumnNames('option_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Eav Attribute Option Swatch')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_attribute_label')
            ->addColumn(Column::editor()->setUnquotedName('attribute_label_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('value')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_label_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('attribute_id', 'store_id'))
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
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Eav Attribute Label')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_form_type')
            ->addColumn(Column::editor()->setUnquotedName('type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('code')->setTypeName(Types::STRING)->setLength(64)->create())
            ->addColumn(Column::editor()->setUnquotedName('label')->setTypeName(Types::STRING)->setLength(255)->create())
            ->addColumn(Column::editor()->setUnquotedName('is_system')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('theme')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('type_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('code', 'theme', 'store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Eav Form Type')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_form_type_entity')
            ->addColumn(Column::editor()->setUnquotedName('type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('entity_type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('type_id', 'entity_type_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('entity_type_id')
                    ->setUnquotedReferencedTableName('eav_entity_type')
                    ->setUnquotedReferencedColumnNames('entity_type_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('type_id')
                    ->setUnquotedReferencedTableName('eav_form_type')
                    ->setUnquotedReferencedColumnNames('type_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Eav Form Type Entity')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_form_fieldset')
            ->addColumn(Column::editor()->setUnquotedName('fieldset_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('code')->setTypeName(Types::STRING)->setLength(64)->create())
            ->addColumn(Column::editor()->setUnquotedName('sort_order')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('fieldset_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('type_id', 'code'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('type_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('type_id')
                    ->setUnquotedReferencedTableName('eav_form_type')
                    ->setUnquotedReferencedColumnNames('type_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Eav Form Fieldset')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_form_fieldset_label')
            ->addColumn(Column::editor()->setUnquotedName('fieldset_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('label')->setTypeName(Types::STRING)->setLength(255)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('fieldset_id', 'store_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('fieldset_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('fieldset_id')
                    ->setUnquotedReferencedTableName('eav_form_fieldset')
                    ->setUnquotedReferencedColumnNames('fieldset_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Eav Form Fieldset Label')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_form_element')
            ->addColumn(Column::editor()->setUnquotedName('element_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('type_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('fieldset_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('attribute_id')->setTypeName(Types::SMALLINT)->setUnsigned(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('sort_order')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('element_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('type_id', 'attribute_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('type_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('fieldset_id'))
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
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('fieldset_id')
                    ->setUnquotedReferencedTableName('eav_form_fieldset')
                    ->setUnquotedReferencedColumnNames('fieldset_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('type_id')
                    ->setUnquotedReferencedTableName('eav_form_type')
                    ->setUnquotedReferencedColumnNames('type_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Eav Form Element')
            ->create(),
    );
};
