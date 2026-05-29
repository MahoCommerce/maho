<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $entityType = $schema->createTable('eav_entity_type');
    $entityType->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $entityType->addColumn('entity_type_code', Types::STRING, ['length' => 50]);
    $entityType->addColumn('entity_model', Types::STRING, ['length' => 255]);
    $entityType->addColumn('attribute_model', Types::STRING, ['length' => 255, 'notnull' => false]);
    $entityType->addColumn('entity_table', Types::STRING, ['length' => 255, 'notnull' => false]);
    $entityType->addColumn('value_table_prefix', Types::STRING, ['length' => 255, 'notnull' => false]);
    $entityType->addColumn('entity_id_field', Types::STRING, ['length' => 255, 'notnull' => false]);
    $entityType->addColumn('is_data_sharing', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $entityType->addColumn('data_sharing_key', Types::STRING, ['length' => 100, 'notnull' => false, 'default' => 'default']);
    $entityType->addColumn('default_attribute_set_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $entityType->addColumn('increment_model', Types::STRING, ['length' => 255, 'notnull' => false, 'default' => '']);
    $entityType->addColumn('increment_per_store', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $entityType->addColumn('increment_pad_length', Types::SMALLINT, ['unsigned' => true, 'default' => 8]);
    $entityType->addColumn('increment_pad_char', Types::STRING, ['length' => 1, 'default' => '0']);
    $entityType->addColumn('additional_attribute_table', Types::STRING, ['length' => 255, 'notnull' => false, 'default' => '']);
    $entityType->addColumn('entity_attribute_collection', Types::STRING, ['length' => 255, 'notnull' => false]);
    $entityType->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_type_id')->create(),
    );
    $entityType->addIndex(['entity_type_code'], 'idx_eav_entity_type_entity_type_code');
    $entityType->setComment('Eav Entity Type');

    // created_at / updated_at defaults normalized to CURRENT_TIMESTAMP by
    // upgrade-1.6.0.2-1.6.0.3.php (MySQL TIMESTAMP_INIT).
    $entity = $schema->createTable('eav_entity');
    $entity->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $entity->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $entity->addColumn('attribute_set_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $entity->addColumn('increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $entity->addColumn('parent_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $entity->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $entity->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $entity->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $entity->addColumn('is_active', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $entity->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $entity->addIndex(['entity_type_id'], 'idx_eav_entity_entity_type_id');
    $entity->addIndex(['store_id'], 'idx_eav_entity_store_id');
    $entity->addForeignKeyConstraint('eav_entity_type', ['entity_type_id'], ['entity_type_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_entity_entity_type');
    $entity->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_entity_store');
    $entity->setComment('Eav Entity');

    // Five structurally near-identical value tables keyed by backend_type.
    // Each shares the FK set (entity, entity_type, store) and the same index
    // shape, with only the `value` column type differing per backend.
    $valueTables = [
        'eav_entity_datetime' => ['type' => Types::DATETIME_MUTABLE, 'options' => ['notnull' => false], 'hasValueIndex' => true],
        'eav_entity_decimal'  => ['type' => Types::DECIMAL,          'options' => ['precision' => 12, 'scale' => 4, 'default' => '0.0000'], 'hasValueIndex' => true],
        'eav_entity_int'      => ['type' => Types::INTEGER,          'options' => ['default' => 0], 'hasValueIndex' => true],
        'eav_entity_text'     => ['type' => Types::TEXT,             'options' => ['length' => 65535], 'hasValueIndex' => false],
        'eav_entity_varchar'  => ['type' => Types::STRING,           'options' => ['length' => 255, 'notnull' => false], 'hasValueIndex' => true],
    ];
    foreach ($valueTables as $tableName => $spec) {
        $t = $schema->createTable($tableName);
        $t->addColumn('value_id', Types::INTEGER, ['autoincrement' => true]);
        $t->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('value', $spec['type'], $spec['options']);
        $t->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
        );
        $short = str_replace('eav_entity_', 'eav_ent_', $tableName);
        $t->addIndex(['entity_type_id'], "idx_{$short}_entity_type_id");
        $t->addIndex(['attribute_id'], "idx_{$short}_attribute_id");
        $t->addIndex(['store_id'], "idx_{$short}_store_id");
        $t->addIndex(['entity_id'], "idx_{$short}_entity_id");
        if ($spec['hasValueIndex']) {
            $t->addIndex(['attribute_id', 'value'], "idx_{$short}_attribute_id_value");
            $t->addIndex(['entity_type_id', 'value'], "idx_{$short}_entity_type_id_value");
        }
        $t->addUniqueIndex(['entity_id', 'attribute_id', 'store_id'], "unq_{$short}_entity_id_attribute_id_store_id");
        $t->addForeignKeyConstraint('eav_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], "fk_{$short}_entity");
        $t->addForeignKeyConstraint('eav_entity_type', ['entity_type_id'], ['entity_type_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], "fk_{$short}_entity_type");
        $t->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], "fk_{$short}_store");
        $t->setComment('Eav Entity Value Prefix');
    }

    $attribute = $schema->createTable('eav_attribute');
    $attribute->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $attribute->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $attribute->addColumn('attribute_code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $attribute->addColumn('attribute_model', Types::STRING, ['length' => 255, 'notnull' => false]);
    $attribute->addColumn('backend_model', Types::STRING, ['length' => 255, 'notnull' => false]);
    $attribute->addColumn('backend_type', Types::STRING, ['length' => 8, 'default' => 'static']);
    $attribute->addColumn('backend_table', Types::STRING, ['length' => 255, 'notnull' => false]);
    $attribute->addColumn('frontend_model', Types::STRING, ['length' => 255, 'notnull' => false]);
    $attribute->addColumn('frontend_input', Types::STRING, ['length' => 50, 'notnull' => false]);
    $attribute->addColumn('frontend_label', Types::STRING, ['length' => 255, 'notnull' => false]);
    $attribute->addColumn('frontend_class', Types::STRING, ['length' => 255, 'notnull' => false]);
    $attribute->addColumn('source_model', Types::STRING, ['length' => 255, 'notnull' => false]);
    $attribute->addColumn('is_required', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $attribute->addColumn('is_user_defined', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $attribute->addColumn('default_value', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $attribute->addColumn('is_unique', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $attribute->addColumn('note', Types::STRING, ['length' => 255, 'notnull' => false]);
    $attribute->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_id')->create(),
    );
    $attribute->addUniqueIndex(['entity_type_id', 'attribute_code'], 'unq_eav_attribute_entity_type_id_attribute_code');
    // Legacy install declared idx_eav_attribute_entity_type_id twice; keep only one.
    $attribute->addIndex(['entity_type_id'], 'idx_eav_attribute_entity_type_id');
    $attribute->addForeignKeyConstraint('eav_entity_type', ['entity_type_id'], ['entity_type_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_attribute_entity_type');
    $attribute->setComment('Eav Attribute');

    $entityStore = $schema->createTable('eav_entity_store');
    $entityStore->addColumn('entity_store_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $entityStore->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $entityStore->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $entityStore->addColumn('increment_prefix', Types::STRING, ['length' => 20, 'notnull' => false]);
    $entityStore->addColumn('increment_last_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $entityStore->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_store_id')->create(),
    );
    $entityStore->addIndex(['entity_type_id'], 'idx_eav_entity_store_entity_type_id');
    $entityStore->addIndex(['store_id'], 'idx_eav_entity_store_store_id');
    $entityStore->addForeignKeyConstraint('eav_entity_type', ['entity_type_id'], ['entity_type_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_entity_store_entity_type');
    $entityStore->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_entity_store_store');
    $entityStore->setComment('Eav Entity Store');

    $attrSet = $schema->createTable('eav_attribute_set');
    $attrSet->addColumn('attribute_set_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $attrSet->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $attrSet->addColumn('attribute_set_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $attrSet->addColumn('sort_order', Types::SMALLINT, ['default' => 0]);
    $attrSet->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_set_id')->create(),
    );
    $attrSet->addUniqueIndex(['entity_type_id', 'attribute_set_name'], 'unq_eav_attribute_set_entity_type_id_attribute_set_name');
    $attrSet->addIndex(['entity_type_id', 'sort_order'], 'idx_eav_attribute_set_entity_type_id_sort_order');
    $attrSet->addForeignKeyConstraint('eav_entity_type', ['entity_type_id'], ['entity_type_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_attribute_set_entity_type');
    $attrSet->setComment('Eav Attribute Set');

    $attrGroup = $schema->createTable('eav_attribute_group');
    $attrGroup->addColumn('attribute_group_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $attrGroup->addColumn('attribute_set_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $attrGroup->addColumn('attribute_group_name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $attrGroup->addColumn('sort_order', Types::SMALLINT, ['default' => 0]);
    $attrGroup->addColumn('default_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $attrGroup->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_group_id')->create(),
    );
    $attrGroup->addUniqueIndex(['attribute_set_id', 'attribute_group_name'], 'unq_eav_attribute_group_attribute_set_id_attribute_group_name');
    $attrGroup->addIndex(['attribute_set_id', 'sort_order'], 'idx_eav_attribute_group_attribute_set_id_sort_order');
    $attrGroup->addForeignKeyConstraint('eav_attribute_set', ['attribute_set_id'], ['attribute_set_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_attribute_group_attribute_set');
    $attrGroup->setComment('Eav Attribute Group');

    $entityAttr = $schema->createTable('eav_entity_attribute');
    $entityAttr->addColumn('entity_attribute_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $entityAttr->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $entityAttr->addColumn('attribute_set_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $entityAttr->addColumn('attribute_group_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $entityAttr->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $entityAttr->addColumn('sort_order', Types::SMALLINT, ['default' => 0]);
    $entityAttr->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_attribute_id')->create(),
    );
    $entityAttr->addUniqueIndex(['attribute_set_id', 'attribute_id'], 'unq_eav_entity_attribute_attribute_set_id_attribute_id');
    $entityAttr->addUniqueIndex(['attribute_group_id', 'attribute_id'], 'unq_eav_entity_attribute_attribute_group_id_attribute_id');
    $entityAttr->addIndex(['attribute_set_id', 'sort_order'], 'idx_eav_entity_attribute_attribute_set_id_sort_order');
    $entityAttr->addIndex(['attribute_id'], 'idx_eav_entity_attribute_attribute_id');
    $entityAttr->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_entity_attribute_attribute');
    $entityAttr->addForeignKeyConstraint('eav_attribute_group', ['attribute_group_id'], ['attribute_group_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_entity_attribute_attribute_group');
    $entityAttr->setComment('Eav Entity Attributes');

    $attrOption = $schema->createTable('eav_attribute_option');
    $attrOption->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $attrOption->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $attrOption->addColumn('sort_order', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $attrOption->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_id')->create(),
    );
    $attrOption->addIndex(['attribute_id'], 'idx_eav_attribute_option_attribute_id');
    $attrOption->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_attribute_option_attribute');
    $attrOption->setComment('Eav Attribute Option');

    $attrOptionValue = $schema->createTable('eav_attribute_option_value');
    $attrOptionValue->addColumn('value_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $attrOptionValue->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $attrOptionValue->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $attrOptionValue->addColumn('value', Types::STRING, ['length' => 255, 'notnull' => false]);
    $attrOptionValue->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $attrOptionValue->addIndex(['option_id'], 'idx_eav_attribute_option_value_option_id');
    $attrOptionValue->addIndex(['store_id'], 'idx_eav_attribute_option_value_store_id');
    $attrOptionValue->addForeignKeyConstraint('eav_attribute_option', ['option_id'], ['option_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_attribute_option_value_option');
    $attrOptionValue->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_attribute_option_value_store');
    $attrOptionValue->setComment('Eav Attribute Option Value');

    // Table added by legacy upgrade-1.6.0.1-1.6.0.2.php
    $attrOptionSwatch = $schema->createTable('eav_attribute_option_swatch');
    $attrOptionSwatch->addColumn('value_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $attrOptionSwatch->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $attrOptionSwatch->addColumn('value', Types::STRING, ['length' => 255, 'notnull' => false]);
    $attrOptionSwatch->addColumn('filename', Types::STRING, ['length' => 255, 'notnull' => false]);
    $attrOptionSwatch->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $attrOptionSwatch->addUniqueIndex(['option_id'], 'unq_eav_attribute_option_swatch_option_id');
    $attrOptionSwatch->addForeignKeyConstraint('eav_attribute_option', ['option_id'], ['option_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_attribute_option_swatch_option');
    $attrOptionSwatch->setComment('Eav Attribute Option Swatch');

    $attrLabel = $schema->createTable('eav_attribute_label');
    $attrLabel->addColumn('attribute_label_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $attrLabel->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $attrLabel->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $attrLabel->addColumn('value', Types::STRING, ['length' => 255, 'notnull' => false]);
    $attrLabel->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_label_id')->create(),
    );
    $attrLabel->addIndex(['attribute_id'], 'idx_eav_attribute_label_attribute_id');
    $attrLabel->addIndex(['store_id'], 'idx_eav_attribute_label_store_id');
    $attrLabel->addIndex(['attribute_id', 'store_id'], 'idx_eav_attribute_label_attribute_id_store_id');
    $attrLabel->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_attribute_label_attribute');
    $attrLabel->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_attribute_label_store');
    $attrLabel->setComment('Eav Attribute Label');

    $formType = $schema->createTable('eav_form_type');
    $formType->addColumn('type_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $formType->addColumn('code', Types::STRING, ['length' => 64]);
    $formType->addColumn('label', Types::STRING, ['length' => 255]);
    $formType->addColumn('is_system', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $formType->addColumn('theme', Types::STRING, ['length' => 64, 'notnull' => false]);
    $formType->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $formType->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('type_id')->create(),
    );
    $formType->addUniqueIndex(['code', 'theme', 'store_id'], 'unq_eav_form_type_code_theme_store_id');
    $formType->addIndex(['store_id'], 'idx_eav_form_type_store_id');
    $formType->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_form_type_store');
    $formType->setComment('Eav Form Type');

    $formTypeEntity = $schema->createTable('eav_form_type_entity');
    $formTypeEntity->addColumn('type_id', Types::SMALLINT, ['unsigned' => true]);
    $formTypeEntity->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true]);
    $formTypeEntity->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('type_id', 'entity_type_id')->create(),
    );
    $formTypeEntity->addIndex(['entity_type_id'], 'idx_eav_form_type_entity_entity_type_id');
    $formTypeEntity->addForeignKeyConstraint('eav_entity_type', ['entity_type_id'], ['entity_type_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_form_type_entity_entity_type');
    $formTypeEntity->addForeignKeyConstraint('eav_form_type', ['type_id'], ['type_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_form_type_entity_type');
    $formTypeEntity->setComment('Eav Form Type Entity');

    $formFieldset = $schema->createTable('eav_form_fieldset');
    $formFieldset->addColumn('fieldset_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $formFieldset->addColumn('type_id', Types::SMALLINT, ['unsigned' => true]);
    $formFieldset->addColumn('code', Types::STRING, ['length' => 64]);
    $formFieldset->addColumn('sort_order', Types::INTEGER, ['default' => 0]);
    $formFieldset->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('fieldset_id')->create(),
    );
    $formFieldset->addUniqueIndex(['type_id', 'code'], 'unq_eav_form_fieldset_type_id_code');
    $formFieldset->addIndex(['type_id'], 'idx_eav_form_fieldset_type_id');
    $formFieldset->addForeignKeyConstraint('eav_form_type', ['type_id'], ['type_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_form_fieldset_type');
    $formFieldset->setComment('Eav Form Fieldset');

    $formFieldsetLabel = $schema->createTable('eav_form_fieldset_label');
    $formFieldsetLabel->addColumn('fieldset_id', Types::SMALLINT, ['unsigned' => true]);
    $formFieldsetLabel->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $formFieldsetLabel->addColumn('label', Types::STRING, ['length' => 255]);
    $formFieldsetLabel->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('fieldset_id', 'store_id')->create(),
    );
    $formFieldsetLabel->addIndex(['fieldset_id'], 'idx_eav_form_fieldset_label_fieldset_id');
    $formFieldsetLabel->addIndex(['store_id'], 'idx_eav_form_fieldset_label_store_id');
    $formFieldsetLabel->addForeignKeyConstraint('eav_form_fieldset', ['fieldset_id'], ['fieldset_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_form_fieldset_label_fieldset');
    $formFieldsetLabel->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_form_fieldset_label_store');
    $formFieldsetLabel->setComment('Eav Form Fieldset Label');

    $formElement = $schema->createTable('eav_form_element');
    $formElement->addColumn('element_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $formElement->addColumn('type_id', Types::SMALLINT, ['unsigned' => true]);
    $formElement->addColumn('fieldset_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $formElement->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true]);
    $formElement->addColumn('sort_order', Types::INTEGER, ['default' => 0]);
    $formElement->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('element_id')->create(),
    );
    $formElement->addUniqueIndex(['type_id', 'attribute_id'], 'unq_eav_form_element_type_id_attribute_id');
    $formElement->addIndex(['type_id'], 'idx_eav_form_element_type_id');
    $formElement->addIndex(['fieldset_id'], 'idx_eav_form_element_fieldset_id');
    $formElement->addIndex(['attribute_id'], 'idx_eav_form_element_attribute_id');
    $formElement->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_form_element_attribute');
    $formElement->addForeignKeyConstraint('eav_form_fieldset', ['fieldset_id'], ['fieldset_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'], 'fk_eav_form_element_fieldset');
    $formElement->addForeignKeyConstraint('eav_form_type', ['type_id'], ['type_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'], 'fk_eav_form_element_type');
    $formElement->setComment('Eav Form Element');
};
