<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $destination = $schema->createTable('feedmanager_destination');
    $destination->addColumn('destination_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $destination->addColumn('name', Types::STRING, ['length' => 255]);
    $destination->addColumn('type', Types::STRING, ['length' => 50]);
    $destination->addColumn('config', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $destination->addColumn('is_enabled', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $destination->addColumn('last_upload_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $destination->addColumn('last_upload_status', Types::STRING, ['length' => 20, 'notnull' => false]);
    $destination->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $destination->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $destination->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('destination_id')->create(),
    );
    $destination->addIndex(['type']);
    $destination->addIndex(['is_enabled']);
    $destination->setComment('Feed Manager - Upload Destinations');

    $feed = $schema->createTable('feedmanager_feed');
    $feed->addColumn('feed_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $feed->addColumn('name', Types::STRING, ['length' => 255]);
    $feed->addColumn('platform', Types::STRING, ['length' => 50]);
    $feed->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $feed->addColumn('is_enabled', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $feed->addColumn('filename', Types::STRING, ['length' => 255]);
    $feed->addColumn('file_format', Types::STRING, ['length' => 10, 'default' => 'xml']);
    $feed->addColumn('generation_time', Types::STRING, ['length' => 8, 'default' => '03:00:00']);
    $feed->addColumn('configurable_mode', Types::STRING, ['length' => 20, 'default' => 'children_only']);
    $feed->addColumn('destination_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $feed->addColumn('auto_upload', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $feed->addColumn('schedule', Types::STRING, ['length' => 50, 'notnull' => false]);
    $feed->addColumn('product_filters', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $feed->addColumn('conditions_serialized', Types::TEXT, ['length' => 1048576, 'notnull' => false]);
    $feed->addColumn('exclude_disabled', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $feed->addColumn('exclude_out_of_stock', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $feed->addColumn('include_product_types', Types::STRING, ['length' => 255, 'notnull' => false, 'default' => 'simple']);
    $feed->addColumn('condition_groups', Types::TEXT, ['length' => 1048576, 'notnull' => false]);
    $feed->addColumn('xml_header', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $feed->addColumn('xml_item_template', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $feed->addColumn('xml_footer', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $feed->addColumn('xml_item_tag', Types::STRING, ['length' => 50, 'notnull' => false, 'default' => 'item']);
    $feed->addColumn('xml_structure', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $feed->addColumn('csv_columns', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $feed->addColumn('csv_delimiter', Types::STRING, ['length' => 5, 'notnull' => false, 'default' => ',']);
    $feed->addColumn('csv_enclosure', Types::STRING, ['length' => 5, 'notnull' => false, 'default' => '"']);
    $feed->addColumn('csv_include_header', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 1]);
    $feed->addColumn('json_structure', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $feed->addColumn('json_root_key', Types::STRING, ['length' => 50, 'notnull' => false, 'default' => 'products']);
    $feed->addColumn('format_preset', Types::STRING, ['length' => 32, 'notnull' => false, 'default' => 'english']);
    $feed->addColumn('price_currency', Types::STRING, ['length' => 10, 'notnull' => false]);
    $feed->addColumn('price_decimals', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 2]);
    $feed->addColumn('price_decimal_point', Types::STRING, ['length' => 5, 'notnull' => false, 'default' => '.']);
    $feed->addColumn('price_thousands_sep', Types::STRING, ['length' => 5, 'notnull' => false, 'default' => '']);
    $feed->addColumn('price_currency_suffix', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $feed->addColumn('tax_mode', Types::STRING, ['length' => 10, 'notnull' => false, 'default' => 'incl']);
    $feed->addColumn('use_parent_value', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 1]);
    $feed->addColumn('exclude_category_url', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 1]);
    $feed->addColumn('no_image_url', Types::STRING, ['length' => 500, 'notnull' => false]);
    $feed->addColumn('gzip_compression', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $feed->addColumn('notification_mode', Types::STRING, ['length' => 20, 'default' => 'none']);
    $feed->addColumn('notification_frequency', Types::STRING, ['length' => 20, 'default' => 'once_until_success']);
    $feed->addColumn('notification_email', Types::STRING, ['length' => 255, 'notnull' => false]);
    $feed->addColumn('notification_sent', Types::SMALLINT, ['default' => 0]);
    $feed->addColumn('last_generated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $feed->addColumn('last_product_count', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $feed->addColumn('last_file_size', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $feed->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $feed->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $feed->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('feed_id')->create(),
    );
    $feed->addIndex(['platform']);
    $feed->addIndex(['is_enabled']);
    $feed->addIndex(['store_id']);
    $feed->addIndex(['destination_id']);
    $feed->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onDelete' => 'CASCADE'],
    );
    $feed->addForeignKeyConstraint(
        'feedmanager_destination',
        ['destination_id'],
        ['destination_id'],
        ['onDelete' => 'SET NULL'],
    );
    $feed->setComment('Feed Manager - Feeds');

    $attributeMapping = $schema->createTable('feedmanager_attribute_mapping');
    $attributeMapping->addColumn('mapping_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $attributeMapping->addColumn('feed_id', Types::INTEGER, ['unsigned' => true]);
    $attributeMapping->addColumn('platform_attribute', Types::STRING, ['length' => 100]);
    $attributeMapping->addColumn('source_type', Types::STRING, ['length' => 20]);
    $attributeMapping->addColumn('source_value', Types::TEXT, ['length' => 65535]);
    $attributeMapping->addColumn('conditions', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $attributeMapping->addColumn('transformers', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $attributeMapping->addColumn('sort_order', Types::INTEGER, ['default' => 0]);
    $attributeMapping->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('mapping_id')->create(),
    );
    $attributeMapping->addIndex(['feed_id', 'platform_attribute']);
    $attributeMapping->addForeignKeyConstraint(
        'feedmanager_feed',
        ['feed_id'],
        ['feed_id'],
        ['onDelete' => 'CASCADE'],
    );
    $attributeMapping->setComment('Feed Manager - Attribute Mappings');

    $categoryMapping = $schema->createTable('feedmanager_category_mapping');
    $categoryMapping->addColumn('mapping_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $categoryMapping->addColumn('platform', Types::STRING, ['length' => 50]);
    $categoryMapping->addColumn('category_id', Types::INTEGER, ['unsigned' => true]);
    $categoryMapping->addColumn('platform_category_id', Types::STRING, ['length' => 100]);
    $categoryMapping->addColumn('platform_category_path', Types::STRING, ['length' => 500]);
    $categoryMapping->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('mapping_id')->create(),
    );
    $categoryMapping->addUniqueIndex(['platform', 'category_id']);
    $categoryMapping->addForeignKeyConstraint('catalog_category_entity', ['category_id'], ['entity_id'], ['onDelete' => 'CASCADE']);
    $categoryMapping->setComment('Feed Manager - Category Mappings');

    $log = $schema->createTable('feedmanager_log');
    $log->addColumn('log_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $log->addColumn('feed_id', Types::INTEGER, ['unsigned' => true]);
    $log->addColumn('started_at', Types::DATETIME_MUTABLE);
    $log->addColumn('completed_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $log->addColumn('status', Types::STRING, ['length' => 20, 'default' => 'running']);
    $log->addColumn('product_count', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $log->addColumn('error_count', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $log->addColumn('errors', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $log->addColumn('file_path', Types::STRING, ['length' => 255, 'notnull' => false]);
    $log->addColumn('file_size', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $log->addColumn('upload_status', Types::STRING, ['length' => 20, 'notnull' => false]);
    $log->addColumn('uploaded_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $log->addColumn('upload_message', Types::STRING, ['length' => 500, 'notnull' => false]);
    $log->addColumn('destination_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $log->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('log_id')->create(),
    );
    $log->addIndex(['feed_id', 'started_at']);
    $log->addForeignKeyConstraint(
        'feedmanager_feed',
        ['feed_id'],
        ['feed_id'],
        ['onDelete' => 'CASCADE'],
    );
    $log->setComment('Feed Manager - Generation Logs');

    $dynamicRule = $schema->createTable('feedmanager_dynamic_rule');
    $dynamicRule->addColumn('rule_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $dynamicRule->addColumn('name', Types::STRING, ['length' => 255]);
    $dynamicRule->addColumn('code', Types::STRING, ['length' => 100]);
    $dynamicRule->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $dynamicRule->addColumn('is_system', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $dynamicRule->addColumn('is_enabled', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $dynamicRule->addColumn('cases', Types::TEXT, ['length' => 16777215, 'notnull' => false]);
    $dynamicRule->addColumn('sort_order', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $dynamicRule->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $dynamicRule->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $dynamicRule->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id')->create(),
    );
    $dynamicRule->addUniqueIndex(['code']);
    $dynamicRule->addIndex(['is_enabled', 'sort_order']);
    $dynamicRule->setComment('FeedManager Dynamic Attribute Rules');
};
