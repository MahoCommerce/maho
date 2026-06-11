<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $entity = $schema->createTable('customer_entity');
    $entity->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $entity->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $entity->addColumn('attribute_set_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $entity->addColumn('website_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $entity->addColumn('email', Types::STRING, ['length' => 255, 'notnull' => false]);
    $entity->addColumn('group_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $entity->addColumn('increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $entity->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $entity->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $entity->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $entity->addColumn('is_active', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $entity->addColumn('disable_auto_group_change', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $entity->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $entity->addIndex(['store_id']);
    $entity->addIndex(['entity_type_id']);
    $entity->addUniqueIndex(['email', 'website_id']);
    $entity->addIndex(['website_id']);
    $entity->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL']);
    $entity->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL']);
    $entity->setComment('Customer Entity');

    $addrEntity = $schema->createTable('customer_address_entity');
    $addrEntity->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $addrEntity->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $addrEntity->addColumn('attribute_set_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $addrEntity->addColumn('increment_id', Types::STRING, ['length' => 50, 'notnull' => false]);
    $addrEntity->addColumn('parent_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $addrEntity->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $addrEntity->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $addrEntity->addColumn('is_active', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $addrEntity->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $addrEntity->addIndex(['parent_id']);
    $addrEntity->addForeignKeyConstraint('customer_entity', ['parent_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $addrEntity->setComment('Customer Address Entity');

    // 10 structurally near-identical EAV value tables (5 typed per parent entity).
    // Each shares the FK set (parent entity, eav_attribute, eav_entity_type) and
    // the same index shape, with only the `value` column type differing per backend.
    $valueTables = [
        'customer_address_entity_datetime' => ['parent' => 'customer_address_entity', 'type' => Types::DATETIME_MUTABLE, 'options' => ['notnull' => false], 'hasValueIndex' => true],
        'customer_address_entity_decimal'  => ['parent' => 'customer_address_entity', 'type' => Types::DECIMAL,          'options' => ['precision' => 12, 'scale' => 4, 'default' => '0.0000'], 'hasValueIndex' => true],
        'customer_address_entity_int'      => ['parent' => 'customer_address_entity', 'type' => Types::INTEGER,          'options' => ['default' => 0], 'hasValueIndex' => true],
        'customer_address_entity_text'     => ['parent' => 'customer_address_entity', 'type' => Types::TEXT,             'options' => ['length' => 65535], 'hasValueIndex' => false],
        'customer_address_entity_varchar'  => ['parent' => 'customer_address_entity', 'type' => Types::STRING,           'options' => ['length' => 255, 'notnull' => false], 'hasValueIndex' => true],
        'customer_entity_datetime'         => ['parent' => 'customer_entity',         'type' => Types::DATETIME_MUTABLE, 'options' => ['notnull' => false], 'hasValueIndex' => true],
        'customer_entity_decimal'          => ['parent' => 'customer_entity',         'type' => Types::DECIMAL,          'options' => ['precision' => 12, 'scale' => 4, 'default' => '0.0000'], 'hasValueIndex' => true],
        'customer_entity_int'              => ['parent' => 'customer_entity',         'type' => Types::INTEGER,          'options' => ['default' => 0], 'hasValueIndex' => true],
        'customer_entity_text'             => ['parent' => 'customer_entity',         'type' => Types::TEXT,             'options' => ['length' => 65535], 'hasValueIndex' => false],
        'customer_entity_varchar'          => ['parent' => 'customer_entity',         'type' => Types::STRING,           'options' => ['length' => 255, 'notnull' => false], 'hasValueIndex' => true],
    ];
    foreach ($valueTables as $tableName => $spec) {
        $t = $schema->createTable($tableName);
        $t->addColumn('value_id', Types::INTEGER, ['autoincrement' => true]);
        $t->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
        $t->addColumn('value', $spec['type'], $spec['options']);
        $t->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
        );
        $t->addUniqueIndex(['entity_id', 'attribute_id']);
        $t->addIndex(['entity_type_id']);
        $t->addIndex(['attribute_id']);
        $t->addIndex(['entity_id']);
        if ($spec['hasValueIndex']) {
            $t->addIndex(['entity_id', 'attribute_id', 'value']);
        }
        $t->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
        $t->addForeignKeyConstraint($spec['parent'], ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
        $t->addForeignKeyConstraint('eav_entity_type', ['entity_type_id'], ['entity_type_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
        $t->setComment(ucwords(str_replace('_', ' ', $tableName)));
    }

    $group = $schema->createTable('customer_group');
    $group->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $group->addColumn('customer_group_code', Types::STRING, ['length' => 32]);
    $group->addColumn('tax_class_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $group->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('customer_group_id')->create(),
    );
    $group->setComment('Customer Group');

    $eavAttr = $schema->createTable('customer_eav_attribute');
    $eavAttr->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true]);
    $eavAttr->addColumn('is_visible', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $eavAttr->addColumn('input_filter', Types::STRING, ['length' => 255, 'notnull' => false]);
    $eavAttr->addColumn('multiline_count', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $eavAttr->addColumn('validate_rules', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $eavAttr->addColumn('is_system', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $eavAttr->addColumn('sort_order', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $eavAttr->addColumn('data_model', Types::STRING, ['length' => 255, 'notnull' => false]);
    $eavAttr->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_id')->create(),
    );
    $eavAttr->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $eavAttr->setComment('Customer Eav Attribute');

    $formAttr = $schema->createTable('customer_form_attribute');
    $formAttr->addColumn('form_code', Types::STRING, ['length' => 32]);
    $formAttr->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true]);
    $formAttr->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('form_code', 'attribute_id')->create(),
    );
    $formAttr->addIndex(['attribute_id']);
    $formAttr->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $formAttr->setComment('Customer Form Attribute');

    $eavAttrWebsite = $schema->createTable('customer_eav_attribute_website');
    $eavAttrWebsite->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true]);
    $eavAttrWebsite->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $eavAttrWebsite->addColumn('is_visible', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $eavAttrWebsite->addColumn('is_required', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $eavAttrWebsite->addColumn('default_value', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $eavAttrWebsite->addColumn('multiline_count', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $eavAttrWebsite->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_id', 'website_id')->create(),
    );
    $eavAttrWebsite->addIndex(['website_id']);
    $eavAttrWebsite->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $eavAttrWebsite->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $eavAttrWebsite->setComment('Customer Eav Attribute Website');

    $flowPassword = $schema->createTable('customer_flowpassword');
    $flowPassword->addColumn('flowpassword_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $flowPassword->addColumn('ip', Types::STRING, ['length' => 50]);
    $flowPassword->addColumn('email', Types::STRING, ['length' => 255]);
    // Model _beforeSave always populates requested_date with formatDateForDb('now');
    // no DB-level default needed.
    $flowPassword->addColumn('requested_date', Types::STRING, ['length' => 255]);
    $flowPassword->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('flowpassword_id')->create(),
    );
    $flowPassword->addIndex(['email']);
    $flowPassword->addIndex(['ip']);
    $flowPassword->addIndex(['requested_date']);
    $flowPassword->setComment('Customer flow password');
};
