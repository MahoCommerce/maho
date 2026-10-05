<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
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
            ->setUnquotedName('feedmanager_destination')
            ->addColumn(Schema::column('destination_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('name', Types::STRING, length: 255))
            ->addColumn(Schema::column('type', Types::STRING, length: 50))
            ->addColumn(Schema::column('config', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('is_enabled', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('last_upload_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('last_upload_status', Types::STRING, length: 20, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('destination_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('type'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_enabled'))
            ->setComment('Feed Manager - Upload Destinations')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('feedmanager_feed')
            ->addColumn(Schema::column('feed_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('name', Types::STRING, length: 255))
            ->addColumn(Schema::column('platform', Types::STRING, length: 50))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('is_enabled', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('filename', Types::STRING, length: 255))
            ->addColumn(Schema::column('file_format', Types::STRING, length: 10, default: 'xml'))
            ->addColumn(Schema::column('generation_time', Types::STRING, length: 8, default: '03:00:00'))
            ->addColumn(Schema::column('configurable_mode', Types::STRING, length: 20, default: 'children_only'))
            ->addColumn(Schema::column('destination_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('auto_upload', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('schedule', Types::STRING, length: 50, notNull: false))
            ->addColumn(Schema::column('product_filters', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('conditions_serialized', Types::TEXT, length: 1048576, notNull: false))
            ->addColumn(Schema::column('exclude_disabled', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('exclude_out_of_stock', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('include_product_types', Types::STRING, length: 255, notNull: false, default: 'simple'))
            ->addColumn(Schema::column('condition_groups', Types::TEXT, length: 1048576, notNull: false))
            ->addColumn(Schema::column('xml_header', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('xml_item_template', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('xml_footer', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('xml_item_tag', Types::STRING, length: 50, notNull: false, default: 'item'))
            ->addColumn(Schema::column('xml_structure', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('csv_columns', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('csv_delimiter', Types::STRING, length: 5, notNull: false, default: ','))
            ->addColumn(Schema::column('csv_enclosure', Types::STRING, length: 5, notNull: false, default: '"'))
            ->addColumn(Schema::column('csv_include_header', Types::SMALLINT, unsigned: true, notNull: false, default: 1))
            ->addColumn(Schema::column('json_structure', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('json_root_key', Types::STRING, length: 50, notNull: false, default: 'products'))
            ->addColumn(Schema::column('format_preset', Types::STRING, length: 32, notNull: false, default: 'english'))
            ->addColumn(Schema::column('price_currency', Types::STRING, length: 10, notNull: false))
            ->addColumn(Schema::column('price_decimals', Types::SMALLINT, unsigned: true, notNull: false, default: 2))
            ->addColumn(Schema::column('price_decimal_point', Types::STRING, length: 5, notNull: false, default: '.'))
            ->addColumn(Schema::column('price_thousands_sep', Types::STRING, length: 5, notNull: false, default: ''))
            ->addColumn(Schema::column('price_currency_suffix', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('tax_mode', Types::STRING, length: 10, notNull: false, default: 'incl'))
            ->addColumn(Schema::column('use_parent_value', Types::SMALLINT, unsigned: true, notNull: false, default: 1))
            ->addColumn(Schema::column('exclude_category_url', Types::SMALLINT, unsigned: true, notNull: false, default: 1))
            ->addColumn(Schema::column('no_image_url', Types::STRING, length: 500, notNull: false))
            ->addColumn(Schema::column('gzip_compression', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('notification_mode', Types::STRING, length: 20, default: 'none'))
            ->addColumn(Schema::column('notification_frequency', Types::STRING, length: 20, default: 'once_until_success'))
            ->addColumn(Schema::column('notification_email', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('notification_sent', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('last_generated_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('last_product_count', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('last_file_size', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('feed_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('platform'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_enabled'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('destination_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('destination_id')
                    ->setUnquotedReferencedTableName('feedmanager_destination')
                    ->setUnquotedReferencedColumnNames('destination_id')
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Feed Manager - Feeds')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('feedmanager_attribute_mapping')
            ->addColumn(Schema::column('mapping_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('feed_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('platform_attribute', Types::STRING, length: 100))
            ->addColumn(Schema::column('source_type', Types::STRING, length: 20))
            ->addColumn(Schema::column('source_value', Types::TEXT, length: 65535))
            ->addColumn(Schema::column('conditions', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('transformers', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('sort_order', Types::INTEGER, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('mapping_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('feed_id', 'platform_attribute'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('feed_id')
                    ->setUnquotedReferencedTableName('feedmanager_feed')
                    ->setUnquotedReferencedColumnNames('feed_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Feed Manager - Attribute Mappings')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('feedmanager_category_mapping')
            ->addColumn(Schema::column('mapping_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('platform', Types::STRING, length: 50))
            ->addColumn(Schema::column('category_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('platform_category_id', Types::STRING, length: 100))
            ->addColumn(Schema::column('platform_category_path', Types::STRING, length: 500))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('mapping_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('platform', 'category_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('category_id')
                    ->setUnquotedReferencedTableName('catalog_category_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Feed Manager - Category Mappings')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('feedmanager_log')
            ->addColumn(Schema::column('log_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('feed_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('started_at', Types::DATETIME_MUTABLE))
            ->addColumn(Schema::column('completed_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('status', Types::STRING, length: 20, default: 'running'))
            ->addColumn(Schema::column('product_count', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('error_count', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('errors', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('file_path', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('file_size', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('upload_status', Types::STRING, length: 20, notNull: false))
            ->addColumn(Schema::column('uploaded_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('upload_message', Types::STRING, length: 500, notNull: false))
            ->addColumn(Schema::column('destination_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('log_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('feed_id', 'started_at'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('feed_id')
                    ->setUnquotedReferencedTableName('feedmanager_feed')
                    ->setUnquotedReferencedColumnNames('feed_id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Feed Manager - Generation Logs')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('feedmanager_dynamic_rule')
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('name', Types::STRING, length: 255))
            ->addColumn(Schema::column('code', Types::STRING, length: 100))
            ->addColumn(Schema::column('description', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('is_system', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_enabled', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('cases', Types::TEXT, length: 16777215, notNull: false))
            ->addColumn(Schema::column('sort_order', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('code'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_enabled', 'sort_order'))
            ->setComment('FeedManager Dynamic Attribute Rules')
            ->create(),
    );
};
