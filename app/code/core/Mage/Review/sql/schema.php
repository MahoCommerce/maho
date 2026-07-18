<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Review
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $entity = $schema->createTable('review_entity');
    $entity->addColumn('entity_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $entity->addColumn('entity_code', Types::STRING, ['length' => 32]);
    $entity->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $entity->setComment('Review entities');

    $status = $schema->createTable('review_status');
    $status->addColumn('status_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $status->addColumn('status_code', Types::STRING, ['length' => 32]);
    $status->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('status_id')->create(),
    );
    $status->setComment('Review statuses');

    $review = $schema->createTable('review');
    $review->addColumn('review_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $review->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $review->addColumn('entity_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $review->addColumn('entity_pk_value', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $review->addColumn('status_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $review->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('review_id')->create(),
    );
    $review->addIndex(['entity_id']);
    $review->addIndex(['status_id']);
    $review->addIndex(['entity_pk_value']);
    $review->addForeignKeyConstraint(
        'review_entity',
        ['entity_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $review->addForeignKeyConstraint(
        'review_status',
        ['status_id'],
        ['status_id'],
        ['onUpdate' => 'NO ACTION', 'onDelete' => 'NO ACTION'],
    );
    $review->setComment('Review base information');

    $detail = $schema->createTable('review_detail');
    $detail->addColumn('detail_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $detail->addColumn('review_id', Types::BIGINT, ['unsigned' => true, 'default' => 0]);
    $detail->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $detail->addColumn('title', Types::STRING, ['length' => 255]);
    $detail->addColumn('detail', Types::TEXT, ['length' => 65535]);
    $detail->addColumn('nickname', Types::STRING, ['length' => 128]);
    $detail->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $detail->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('detail_id')->create(),
    );
    $detail->addIndex(['review_id']);
    $detail->addIndex(['store_id']);
    $detail->addIndex(['customer_id']);
    $detail->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
    );
    $detail->addForeignKeyConstraint(
        'review',
        ['review_id'],
        ['review_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $detail->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
    );
    $detail->setComment('Review detail information');

    // Physical table name is review_entity_summary (config alias review/review_aggregate).
    $summary = $schema->createTable('review_entity_summary');
    $summary->addColumn('primary_id', Types::BIGINT, ['autoincrement' => true]);
    $summary->addColumn('entity_pk_value', Types::BIGINT, ['default' => 0]);
    $summary->addColumn('entity_type', Types::SMALLINT, ['default' => 0]);
    $summary->addColumn('reviews_count', Types::SMALLINT, ['default' => 0]);
    $summary->addColumn('rating_summary', Types::SMALLINT, ['default' => 0]);
    $summary->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $summary->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('primary_id')->create(),
    );
    $summary->addIndex(['store_id']);
    $summary->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $summary->setComment('Review aggregates');

    $store = $schema->createTable('review_store');
    $store->addColumn('review_id', Types::BIGINT, ['unsigned' => true]);
    $store->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $store->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('review_id', 'store_id')->create(),
    );
    $store->addIndex(['store_id']);
    $store->addForeignKeyConstraint(
        'review',
        ['review_id'],
        ['review_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $store->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $store->setComment('Review Store');
};
