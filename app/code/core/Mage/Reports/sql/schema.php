<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $eventType = $schema->createTable('report_event_types');
    $eventType->addColumn('event_type_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $eventType->addColumn('event_name', Types::STRING, ['length' => 64]);
    $eventType->addColumn('customer_login', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $eventType->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('event_type_id')->create(),
    );
    $eventType->setComment('Reports Event Type Table');

    $event = $schema->createTable('report_event');
    $event->addColumn('event_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $event->addColumn('logged_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $event->addColumn('event_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $event->addColumn('object_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $event->addColumn('subject_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $event->addColumn('subtype', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $event->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $event->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('event_id')->create(),
    );
    $event->addIndex(['event_type_id']);
    $event->addIndex(['subject_id']);
    $event->addIndex(['object_id']);
    $event->addIndex(['subtype']);
    $event->addIndex(['store_id']);
    $event->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $event->addForeignKeyConstraint(
        'report_event_types',
        ['event_type_id'],
        ['event_type_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $event->setComment('Reports Event Table');

    $compared = $schema->createTable('report_compared_product_index');
    $compared->addColumn('index_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $compared->addColumn('visitor_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $compared->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $compared->addColumn('product_id', Types::INTEGER, ['unsigned' => true]);
    $compared->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $compared->addColumn('added_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $compared->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('index_id')->create(),
    );
    $compared->addIndex(['visitor_id', 'product_id']);
    $compared->addIndex(['customer_id', 'product_id']);
    $compared->addIndex(['store_id']);
    $compared->addIndex(['added_at']);
    $compared->addIndex(['product_id']);
    $compared->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $compared->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $compared->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
    );
    $compared->setComment('Reports Compared Product Index Table');

    $viewed = $schema->createTable('report_viewed_product_index');
    $viewed->addColumn('index_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $viewed->addColumn('visitor_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $viewed->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $viewed->addColumn('product_id', Types::INTEGER, ['unsigned' => true]);
    $viewed->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $viewed->addColumn('added_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $viewed->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('index_id')->create(),
    );
    $viewed->addIndex(['visitor_id', 'product_id']);
    $viewed->addIndex(['customer_id', 'product_id']);
    $viewed->addIndex(['store_id']);
    $viewed->addIndex(['added_at']);
    $viewed->addIndex(['product_id']);
    $viewed->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $viewed->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $viewed->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
    );
    $viewed->setComment('Reports Viewed Product Index Table');

    // Three structurally identical aggregation tables.
    $aggregationTables = [
        'report_viewed_product_aggregated_daily'   => 'Most Viewed Products Aggregated Daily',
        'report_viewed_product_aggregated_monthly' => 'Most Viewed Products Aggregated Monthly',
        'report_viewed_product_aggregated_yearly'  => 'Most Viewed Products Aggregated Yearly',
    ];
    foreach ($aggregationTables as $tableName => $tableComment) {
        $aggr = $schema->createTable($tableName);
        $aggr->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
        $aggr->addColumn('period', Types::DATE_MUTABLE, ['notnull' => false]);
        $aggr->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
        $aggr->addColumn('product_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
        $aggr->addColumn('product_name', Types::STRING, ['length' => 255, 'notnull' => false]);
        $aggr->addColumn('product_price', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
        $aggr->addColumn('views_num', Types::INTEGER, ['default' => 0]);
        $aggr->addColumn('rating_pos', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
        $aggr->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
        );
        // Abbreviate to keep MySQL identifiers under 64 chars.
        $aggr->addUniqueIndex(['period', 'store_id', 'product_id']);
        $aggr->addIndex(['store_id']);
        $aggr->addIndex(['product_id']);
        $aggr->addForeignKeyConstraint(
            'core_store',
            ['store_id'],
            ['store_id'],
            ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        );
        $aggr->addForeignKeyConstraint(
            'catalog_product_entity',
            ['product_id'],
            ['entity_id'],
            ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        );
        $aggr->setComment($tableComment);
    }
};
