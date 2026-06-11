<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tag
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $tag = $schema->createTable('tag');
    $tag->addColumn('tag_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $tag->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $tag->addColumn('status', Types::SMALLINT, ['default' => 0]);
    $tag->addColumn('first_customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $tag->addColumn('first_store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $tag->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tag_id')->create(),
    );
    $tag->addForeignKeyConstraint(
        'customer_entity',
        ['first_customer_id'],
        ['entity_id'],
        ['onUpdate' => 'NO ACTION', 'onDelete' => 'SET NULL'],
    );
    $tag->addForeignKeyConstraint(
        'core_store',
        ['first_store_id'],
        ['store_id'],
        ['onUpdate' => 'NO ACTION', 'onDelete' => 'SET NULL'],
    );
    $tag->setComment('Tag');

    $relation = $schema->createTable('tag_relation');
    $relation->addColumn('tag_relation_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $relation->addColumn('tag_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $relation->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $relation->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $relation->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $relation->addColumn('active', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $relation->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $relation->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tag_relation_id')->create(),
    );
    $relation->addUniqueIndex(
        ['tag_id', 'customer_id', 'product_id', 'store_id'],
    );
    $relation->addIndex(['product_id']);
    $relation->addIndex(['tag_id']);
    $relation->addIndex(['customer_id']);
    $relation->addIndex(['store_id']);
    $relation->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $relation->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $relation->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $relation->addForeignKeyConstraint(
        'tag',
        ['tag_id'],
        ['tag_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $relation->setComment('Tag Relation');

    $summary = $schema->createTable('tag_summary');
    $summary->addColumn('tag_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $summary->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $summary->addColumn('customers', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $summary->addColumn('products', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $summary->addColumn('uses', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $summary->addColumn('historical_uses', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $summary->addColumn('popularity', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $summary->addColumn('base_popularity', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $summary->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tag_id', 'store_id')->create(),
    );
    $summary->addIndex(['store_id']);
    $summary->addIndex(['tag_id']);
    $summary->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $summary->addForeignKeyConstraint(
        'tag',
        ['tag_id'],
        ['tag_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $summary->setComment('Tag Summary');

    $properties = $schema->createTable('tag_properties');
    $properties->addColumn('tag_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $properties->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $properties->addColumn('base_popularity', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $properties->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tag_id', 'store_id')->create(),
    );
    $properties->addIndex(['store_id']);
    $properties->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $properties->addForeignKeyConstraint(
        'tag',
        ['tag_id'],
        ['tag_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $properties->setComment('Tag Properties');
};
