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
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_entity_type')
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_type_code', Types::STRING, length: 50))
            ->addColumn(Schema::column('entity_model', Types::STRING, length: 255))
            ->addColumn(Schema::column('attribute_model', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('entity_table', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('value_table_prefix', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('entity_id_field', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('is_data_sharing', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('data_sharing_key', Types::STRING, length: 100, notNull: false, default: 'default'))
            ->addColumn(Schema::column('default_attribute_set_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('increment_model', Types::STRING, length: 255, notNull: false, default: ''))
            ->addColumn(Schema::column('increment_per_store', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('increment_pad_length', Types::SMALLINT, unsigned: true, default: 8))
            ->addColumn(Schema::column('increment_pad_char', Types::STRING, length: 1, default: '0'))
            ->addColumn(Schema::column('additional_attribute_table', Types::STRING, length: 255, notNull: false, default: ''))
            ->addColumn(Schema::column('entity_attribute_collection', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_type_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('entity_type_code'))
            ->setComment('Eav Entity Type')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('eav_entity')
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_set_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('increment_id', Types::STRING, length: 50, notNull: false))
            ->addColumn(Schema::column('parent_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, unsigned: true, default: 1))
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
        'eav_entity_datetime' => [
            'value' => Schema::column('value', Types::DATETIME_MUTABLE, notNull: false),
            'hasValueIndex' => true,
        ],
        'eav_entity_decimal' => [
            'value' => Schema::column('value', Types::DECIMAL, precision: 12, scale: 4, default: '0.0000'),
            'hasValueIndex' => true,
        ],
        'eav_entity_int' => [
            'value' => Schema::column('value', Types::INTEGER, default: 0),
            'hasValueIndex' => true,
        ],
        'eav_entity_text' => [
            'value' => Schema::column('value', Types::TEXT, length: 65535),
            'hasValueIndex' => false,
        ],
        'eav_entity_varchar' => [
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
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, default: 0))
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
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('entity_id', 'attribute_id', 'store_id'),
            )
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
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_code', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('attribute_model', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('backend_model', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('backend_type', Types::STRING, length: 8, default: 'static'))
            ->addColumn(Schema::column('backend_table', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('frontend_model', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('frontend_input', Types::STRING, length: 50, notNull: false))
            ->addColumn(Schema::column('frontend_label', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('frontend_class', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('source_model', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('is_required', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_user_defined', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('default_value', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('is_unique', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('note', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('entity_type_id', 'attribute_code'),
            )
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
            ->addColumn(Schema::column('entity_store_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('increment_prefix', Types::STRING, length: 20, notNull: false))
            ->addColumn(Schema::column('increment_last_id', Types::STRING, length: 50, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_store_id')
                    ->create(),
            )
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
            ->addColumn(Schema::column('attribute_set_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_set_name', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('sort_order', Types::SMALLINT, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('attribute_set_id')
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('entity_type_id', 'attribute_set_name'),
            )
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
            ->addColumn(Schema::column('attribute_group_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('attribute_set_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_group_name', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('sort_order', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('default_id', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('attribute_group_id')
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('attribute_set_id', 'attribute_group_name'),
            )
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
            ->addColumn(Schema::column('entity_attribute_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_set_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_group_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('sort_order', Types::SMALLINT, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('entity_attribute_id')
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('attribute_set_id', 'attribute_id'),
            )
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('attribute_group_id', 'attribute_id'),
            )
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
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('sort_order', Types::SMALLINT, unsigned: true, default: 0))
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
            ->addColumn(Schema::column('value_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('value', Types::STRING, length: 255, notNull: false))
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
            ->addColumn(Schema::column('value_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('option_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('value', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('filename', Types::STRING, length: 255, notNull: false))
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
            ->addColumn(Schema::column('attribute_label_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('value', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('attribute_label_id')
                    ->create(),
            )
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
            ->addColumn(Schema::column('type_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('code', Types::STRING, length: 64))
            ->addColumn(Schema::column('label', Types::STRING, length: 255))
            ->addColumn(Schema::column('is_system', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('theme', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
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
            ->addColumn(Schema::column('type_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('entity_type_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('type_id', 'entity_type_id')
                    ->create(),
            )
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
            ->addColumn(Schema::column('fieldset_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('type_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('code', Types::STRING, length: 64))
            ->addColumn(Schema::column('sort_order', Types::INTEGER, default: 0))
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
            ->addColumn(Schema::column('fieldset_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('label', Types::STRING, length: 255))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('fieldset_id', 'store_id')
                    ->create(),
            )
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
            ->addColumn(Schema::column('element_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('type_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('fieldset_id', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('attribute_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('sort_order', Types::INTEGER, default: 0))
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
