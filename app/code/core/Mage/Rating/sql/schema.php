<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rating
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $entity = $schema->createTable('rating_entity');
    $entity->addColumn('entity_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $entity->addColumn('entity_code', Types::STRING, ['length' => 64]);
    $entity->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $entity->addUniqueIndex(['entity_code']);
    $entity->setComment('Rating entities');

    $rating = $schema->createTable('rating');
    $rating->addColumn('rating_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $rating->addColumn('entity_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $rating->addColumn('rating_code', Types::STRING, ['length' => 64]);
    $rating->addColumn('position', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $rating->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rating_id')->create(),
    );
    $rating->addUniqueIndex(['rating_code']);
    $rating->addIndex(['entity_id']);
    $rating->addForeignKeyConstraint(
        'rating_entity',
        ['entity_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $rating->setComment('Ratings');

    $option = $schema->createTable('rating_option');
    $option->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $option->addColumn('rating_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $option->addColumn('code', Types::STRING, ['length' => 32]);
    $option->addColumn('value', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $option->addColumn('position', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $option->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('option_id')->create(),
    );
    $option->addIndex(['rating_id']);
    $option->addForeignKeyConstraint(
        'rating',
        ['rating_id'],
        ['rating_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $option->setComment('Rating options');

    $vote = $schema->createTable('rating_option_vote');
    $vote->addColumn('vote_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $vote->addColumn('option_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $vote->addColumn('remote_ip', Types::STRING, ['length' => 50, 'notnull' => false]);
    $vote->addColumn('remote_ip_long', Types::BINARY, ['length' => 16, 'notnull' => false]);
    $vote->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $vote->addColumn('entity_pk_value', Types::BIGINT, ['unsigned' => true, 'default' => 0]);
    $vote->addColumn('rating_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $vote->addColumn('review_id', Types::BIGINT, ['unsigned' => true, 'notnull' => false]);
    $vote->addColumn('percent', Types::SMALLINT, ['default' => 0]);
    $vote->addColumn('value', Types::SMALLINT, ['default' => 0]);
    $vote->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('vote_id')->create(),
    );
    $vote->addIndex(['option_id']);
    $vote->addForeignKeyConstraint(
        'rating_option',
        ['option_id'],
        ['option_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $vote->addForeignKeyConstraint(
        'review',
        ['review_id'],
        ['review_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $vote->setComment('Rating option values');

    $aggregated = $schema->createTable('rating_option_vote_aggregated');
    $aggregated->addColumn('primary_id', Types::INTEGER, ['autoincrement' => true]);
    $aggregated->addColumn('rating_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $aggregated->addColumn('entity_pk_value', Types::BIGINT, ['unsigned' => true, 'default' => 0]);
    $aggregated->addColumn('vote_count', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $aggregated->addColumn('vote_value_sum', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $aggregated->addColumn('percent', Types::SMALLINT, ['default' => 0]);
    $aggregated->addColumn('percent_approved', Types::SMALLINT, ['notnull' => false, 'default' => 0]);
    $aggregated->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $aggregated->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('primary_id')->create(),
    );
    $aggregated->addIndex(['rating_id']);
    $aggregated->addIndex(['store_id']);
    $aggregated->addForeignKeyConstraint(
        'rating',
        ['rating_id'],
        ['rating_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $aggregated->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $aggregated->setComment('Rating vote aggregated');

    $ratingStore = $schema->createTable('rating_store');
    $ratingStore->addColumn('rating_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $ratingStore->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $ratingStore->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rating_id', 'store_id')->create(),
    );
    $ratingStore->addIndex(['store_id']);
    $ratingStore->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $ratingStore->addForeignKeyConstraint(
        'rating',
        ['rating_id'],
        ['rating_id'],
        ['onDelete' => 'CASCADE'],
    );
    $ratingStore->setComment('Rating Store');

    $ratingTitle = $schema->createTable('rating_title');
    $ratingTitle->addColumn('rating_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $ratingTitle->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $ratingTitle->addColumn('value', Types::STRING, ['length' => 255]);
    $ratingTitle->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rating_id', 'store_id')->create(),
    );
    $ratingTitle->addIndex(['store_id']);
    $ratingTitle->addForeignKeyConstraint(
        'rating',
        ['rating_id'],
        ['rating_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $ratingTitle->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $ratingTitle->setComment('Rating Title');
};
