<?php

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
    // upgrade-1.6.0.0.1-1.6.0.0.2 made logged_at nullable with no default (MySQL only)
    $event->addColumn('logged_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $event->addColumn('event_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $event->addColumn('object_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $event->addColumn('subject_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $event->addColumn('subtype', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $event->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $event->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('event_id')->create(),
    );
    $event->addIndex(['event_type_id'], 'idx_report_event_event_type_id');
    $event->addIndex(['subject_id'], 'idx_report_event_subject_id');
    $event->addIndex(['object_id'], 'idx_report_event_object_id');
    $event->addIndex(['subtype'], 'idx_report_event_subtype');
    $event->addIndex(['store_id'], 'idx_report_event_store_id');
    $event->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_report_event_store',
    );
    $event->addForeignKeyConstraint(
        'report_event_types',
        ['event_type_id'],
        ['event_type_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_report_event_event_type',
    );
    $event->setComment('Reports Event Table');

    $compared = $schema->createTable('report_compared_product_index');
    $compared->addColumn('index_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $compared->addColumn('visitor_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $compared->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $compared->addColumn('product_id', Types::INTEGER, ['unsigned' => true]);
    $compared->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    // upgrade-1.6.0.0.1-1.6.0.0.2 set added_at default to CURRENT_TIMESTAMP (MySQL only)
    $compared->addColumn('added_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $compared->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('index_id')->create(),
    );
    $compared->addIndex(['visitor_id', 'product_id'], 'idx_report_compared_product_index_visitor_product');
    $compared->addIndex(['customer_id', 'product_id'], 'idx_report_compared_product_index_customer_product');
    $compared->addIndex(['store_id'], 'idx_report_compared_product_index_store_id');
    $compared->addIndex(['added_at'], 'idx_report_compared_product_index_added_at');
    $compared->addIndex(['product_id'], 'idx_report_compared_product_index_product_id');
    $compared->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_report_compared_product_index_customer',
    );
    $compared->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_report_compared_product_index_product',
    );
    $compared->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
        'fk_report_compared_product_index_store',
    );
    $compared->setComment('Reports Compared Product Index Table');

    $viewed = $schema->createTable('report_viewed_product_index');
    $viewed->addColumn('index_id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
    $viewed->addColumn('visitor_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $viewed->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $viewed->addColumn('product_id', Types::INTEGER, ['unsigned' => true]);
    $viewed->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    // upgrade-1.6.0.0.1-1.6.0.0.2 set added_at default to CURRENT_TIMESTAMP (MySQL only)
    $viewed->addColumn('added_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $viewed->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('index_id')->create(),
    );
    $viewed->addIndex(['visitor_id', 'product_id'], 'idx_report_viewed_product_index_visitor_product');
    $viewed->addIndex(['customer_id', 'product_id'], 'idx_report_viewed_product_index_customer_product');
    $viewed->addIndex(['store_id'], 'idx_report_viewed_product_index_store_id');
    $viewed->addIndex(['added_at'], 'idx_report_viewed_product_index_added_at');
    $viewed->addIndex(['product_id'], 'idx_report_viewed_product_index_product_id');
    $viewed->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_report_viewed_product_index_customer',
    );
    $viewed->addForeignKeyConstraint(
        'catalog_product_entity',
        ['product_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_report_viewed_product_index_product',
    );
    $viewed->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
        'fk_report_viewed_product_index_store',
    );
    $viewed->setComment('Reports Viewed Product Index Table');

    // Three structurally identical aggregation tables. Added by upgrade-1.6.0.0-1.6.0.0.1.
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
        $shortName = str_replace('report_viewed_product_aggregated_', 'report_view_aggr_', $tableName);
        $aggr->addUniqueIndex(['period', 'store_id', 'product_id'], "unq_{$shortName}_period_store_product");
        $aggr->addIndex(['store_id'], "idx_{$shortName}_store_id");
        $aggr->addIndex(['product_id'], "idx_{$shortName}_product_id");
        $aggr->addForeignKeyConstraint(
            'core_store',
            ['store_id'],
            ['store_id'],
            ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
            "fk_{$shortName}_store",
        );
        $aggr->addForeignKeyConstraint(
            'catalog_product_entity',
            ['product_id'],
            ['entity_id'],
            ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
            "fk_{$shortName}_product",
        );
        $aggr->setComment($tableComment);
    }
};
