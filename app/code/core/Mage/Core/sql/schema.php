<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $resource = $schema->createTable('core_resource');
    $resource->addColumn('code', Types::STRING, ['length' => 50]);
    $resource->addColumn('version', Types::STRING, ['length' => 50, 'notnull' => false]);
    $resource->addColumn('data_version', Types::STRING, ['length' => 50, 'notnull' => false]);
    $resource->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('code')->create(),
    );
    $resource->setComment('Resources');

    $website = $schema->createTable('core_website');
    $website->addColumn('website_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $website->addColumn('code', Types::STRING, ['length' => 32, 'notnull' => false]);
    $website->addColumn('name', Types::STRING, ['length' => 64, 'notnull' => false]);
    $website->addColumn('sort_order', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $website->addColumn('default_group_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $website->addColumn('is_default', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $website->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('website_id')->create(),
    );
    $website->addUniqueIndex(['code']);
    $website->addIndex(['sort_order']);
    $website->addIndex(['default_group_id']);
    $website->setComment('Websites');

    $storeGroup = $schema->createTable('core_store_group');
    $storeGroup->addColumn('group_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $storeGroup->addColumn('website_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $storeGroup->addColumn('name', Types::STRING, ['length' => 255]);
    $storeGroup->addColumn('root_category_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $storeGroup->addColumn('default_store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $storeGroup->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('group_id')->create(),
    );
    $storeGroup->addIndex(['website_id']);
    $storeGroup->addIndex(['default_store_id']);
    $storeGroup->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $storeGroup->setComment('Store Groups');

    $store = $schema->createTable('core_store');
    $store->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'autoincrement' => true]);
    $store->addColumn('code', Types::STRING, ['length' => 32, 'notnull' => false]);
    $store->addColumn('website_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $store->addColumn('group_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $store->addColumn('name', Types::STRING, ['length' => 255]);
    $store->addColumn('sort_order', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $store->addColumn('is_active', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $store->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('store_id')->create(),
    );
    $store->addUniqueIndex(['code']);
    $store->addIndex(['website_id']);
    $store->addIndex(['is_active', 'sort_order']);
    $store->addIndex(['group_id']);
    $store->addForeignKeyConstraint('core_store_group', ['group_id'], ['group_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $store->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $store->setComment('Stores');

    $config = $schema->createTable('core_config_data');
    $config->addColumn('config_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $config->addColumn('scope', Types::STRING, ['length' => 8, 'default' => 'default']);
    $config->addColumn('scope_id', Types::INTEGER, ['default' => 0]);
    $config->addColumn('path', Types::STRING, ['length' => 255, 'default' => 'general']);
    $config->addColumn('value', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $config->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $config->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('config_id')->create(),
    );
    $config->addUniqueIndex(['scope', 'scope_id', 'path']);
    $config->setComment('Config Data');

    $emailTemplate = $schema->createTable('core_email_template');
    $emailTemplate->addColumn('template_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $emailTemplate->addColumn('template_code', Types::STRING, ['length' => 150]);
    $emailTemplate->addColumn('template_text', Types::TEXT, ['length' => 65535]);
    $emailTemplate->addColumn('template_styles', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $emailTemplate->addColumn('template_type', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $emailTemplate->addColumn('template_subject', Types::STRING, ['length' => 200]);
    $emailTemplate->addColumn('template_sender_name', Types::STRING, ['length' => 200, 'notnull' => false]);
    $emailTemplate->addColumn('template_sender_email', Types::STRING, ['length' => 200, 'notnull' => false]);
    $emailTemplate->addColumn('added_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $emailTemplate->addColumn('modified_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $emailTemplate->addColumn('orig_template_code', Types::STRING, ['length' => 200, 'notnull' => false]);
    $emailTemplate->addColumn('orig_template_variables', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $emailTemplate->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('template_id')->create(),
    );
    $emailTemplate->addUniqueIndex(['template_code']);
    $emailTemplate->addIndex(['added_at']);
    $emailTemplate->addIndex(['modified_at']);
    $emailTemplate->setComment('Email Templates');

    $layoutUpdate = $schema->createTable('core_layout_update');
    $layoutUpdate->addColumn('layout_update_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $layoutUpdate->addColumn('handle', Types::STRING, ['length' => 255, 'notnull' => false]);
    $layoutUpdate->addColumn('xml', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $layoutUpdate->addColumn('sort_order', Types::SMALLINT, ['default' => 0]);
    $layoutUpdate->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('layout_update_id')->create(),
    );
    $layoutUpdate->addIndex(['handle']);
    $layoutUpdate->setComment('Layout Updates');

    $layoutLink = $schema->createTable('core_layout_link');
    $layoutLink->addColumn('layout_link_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $layoutLink->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $layoutLink->addColumn('area', Types::STRING, ['length' => 64, 'notnull' => false]);
    $layoutLink->addColumn('package', Types::STRING, ['length' => 64, 'notnull' => false]);
    $layoutLink->addColumn('theme', Types::STRING, ['length' => 64, 'notnull' => false]);
    $layoutLink->addColumn('layout_update_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $layoutLink->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('layout_link_id')->create(),
    );
    $layoutLink->addUniqueIndex(['store_id', 'package', 'theme', 'layout_update_id']);
    $layoutLink->addIndex(['layout_update_id']);
    $layoutLink->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $layoutLink->addForeignKeyConstraint('core_layout_update', ['layout_update_id'], ['layout_update_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $layoutLink->setComment('Layout Link');

    $session = $schema->createTable('core_session');
    $session->addColumn('session_id', Types::STRING, ['length' => 255]);
    $session->addColumn('session_expires', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $session->addColumn('session_data', Types::BLOB, ['length' => 2097152]);
    $session->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('session_id')->create(),
    );
    $session->setComment('Database Sessions Storage');

    $translate = $schema->createTable('core_translate');
    $translate->addColumn('key_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $translate->addColumn('string', Types::STRING, ['length' => 255, 'default' => 'Translate String']);
    $translate->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $translate->addColumn('translate', Types::STRING, ['length' => 255, 'notnull' => false]);
    $translate->addColumn('locale', Types::STRING, ['length' => 20, 'default' => 'en_US']);
    $translate->addColumn('crc_string', Types::BIGINT, ['default' => crc32('Translate String')]);
    $translate->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('key_id')->create(),
    );
    $translate->addUniqueIndex(['store_id', 'locale', 'crc_string', 'string']);
    $translate->addIndex(['store_id']);
    $translate->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $translate->setComment('Translations');

    $urlRewrite = $schema->createTable('core_url_rewrite');
    $urlRewrite->addColumn('url_rewrite_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $urlRewrite->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $urlRewrite->addColumn('id_path', Types::STRING, ['length' => 255, 'notnull' => false]);
    $urlRewrite->addColumn('request_path', Types::STRING, ['length' => 255, 'notnull' => false]);
    $urlRewrite->addColumn('target_path', Types::STRING, ['length' => 255, 'notnull' => false]);
    $urlRewrite->addColumn('is_system', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 1]);
    $urlRewrite->addColumn('options', Types::STRING, ['length' => 255, 'notnull' => false]);
    $urlRewrite->addColumn('description', Types::STRING, ['length' => 255, 'notnull' => false]);
    $urlRewrite->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('url_rewrite_id')->create(),
    );
    $urlRewrite->addUniqueIndex(['request_path', 'store_id']);
    $urlRewrite->addUniqueIndex(['id_path', 'is_system', 'store_id']);
    $urlRewrite->addIndex(['target_path', 'store_id']);
    $urlRewrite->addIndex(['id_path']);
    $urlRewrite->addIndex(['store_id']);
    $urlRewrite->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $urlRewrite->setComment('Url Rewrites');

    $designChange = $schema->createTable('design_change');
    $designChange->addColumn('design_change_id', Types::INTEGER, ['autoincrement' => true]);
    $designChange->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $designChange->addColumn('design', Types::STRING, ['length' => 255, 'notnull' => false]);
    $designChange->addColumn('date_from', Types::DATE_MUTABLE, ['notnull' => false]);
    $designChange->addColumn('date_to', Types::DATE_MUTABLE, ['notnull' => false]);
    $designChange->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('design_change_id')->create(),
    );
    $designChange->addIndex(['store_id']);
    $designChange->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $designChange->setComment('Design Changes');

    $variable = $schema->createTable('core_variable');
    $variable->addColumn('variable_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $variable->addColumn('code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $variable->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $variable->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('variable_id')->create(),
    );
    $variable->addUniqueIndex(['code']);
    $variable->setComment('Variables');

    $variableValue = $schema->createTable('core_variable_value');
    $variableValue->addColumn('value_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $variableValue->addColumn('variable_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $variableValue->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $variableValue->addColumn('plain_value', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $variableValue->addColumn('html_value', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $variableValue->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $variableValue->addUniqueIndex(['variable_id', 'store_id']);
    $variableValue->addIndex(['variable_id']);
    $variableValue->addIndex(['store_id']);
    $variableValue->addForeignKeyConstraint('core_store', ['store_id'], ['store_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $variableValue->addForeignKeyConstraint('core_variable', ['variable_id'], ['variable_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $variableValue->setComment('Variable Value');

    $cache = $schema->createTable('core_cache');
    $cache->addColumn('id', Types::STRING, ['length' => 200]);
    $cache->addColumn('data', Types::BLOB, ['length' => 2097152, 'notnull' => false]);
    $cache->addColumn('create_time', Types::INTEGER, ['notnull' => false]);
    $cache->addColumn('update_time', Types::INTEGER, ['notnull' => false]);
    $cache->addColumn('expire_time', Types::INTEGER, ['notnull' => false]);
    $cache->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
    );
    $cache->addIndex(['expire_time']);
    $cache->setComment('Caches');

    $cacheTag = $schema->createTable('core_cache_tag');
    $cacheTag->addColumn('tag', Types::STRING, ['length' => 100]);
    $cacheTag->addColumn('cache_id', Types::STRING, ['length' => 200]);
    $cacheTag->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('tag', 'cache_id')->create(),
    );
    $cacheTag->addIndex(['cache_id']);
    $cacheTag->setComment('Tag Caches');

    $cacheOption = $schema->createTable('core_cache_option');
    $cacheOption->addColumn('code', Types::STRING, ['length' => 32]);
    $cacheOption->addColumn('value', Types::SMALLINT, ['notnull' => false]);
    $cacheOption->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('code')->create(),
    );
    $cacheOption->setComment('Cache Options');

    $flag = $schema->createTable('core_flag');
    $flag->addColumn('flag_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $flag->addColumn('flag_code', Types::STRING, ['length' => 255]);
    $flag->addColumn('state', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $flag->addColumn('flag_data', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $flag->addColumn('last_update', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $flag->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('flag_id')->create(),
    );
    $flag->addIndex(['last_update']);
    $flag->setComment('Flag');

    $emailQueue = $schema->createTable('core_email_queue');
    $emailQueue->addColumn('message_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $emailQueue->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $emailQueue->addColumn('entity_type', Types::STRING, ['length' => 128, 'notnull' => false]);
    $emailQueue->addColumn('event_type', Types::STRING, ['length' => 128, 'notnull' => false]);
    $emailQueue->addColumn('message_body_hash', Types::STRING, ['length' => 64]);
    $emailQueue->addColumn('message_body', Types::TEXT, ['length' => 1048576]);
    $emailQueue->addColumn('message_parameters', Types::TEXT, ['length' => 65535]);
    $emailQueue->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $emailQueue->addColumn('processed_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $emailQueue->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('message_id')->create(),
    );
    $emailQueue->addIndex(['entity_id', 'entity_type', 'event_type', 'message_body_hash']);
    $emailQueue->setComment('Email Queue');

    $emailRecipients = $schema->createTable('core_email_queue_recipients');
    $emailRecipients->addColumn('recipient_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $emailRecipients->addColumn('message_id', Types::INTEGER, ['unsigned' => true]);
    $emailRecipients->addColumn('recipient_email', Types::STRING, ['length' => 128]);
    $emailRecipients->addColumn('recipient_name', Types::STRING, ['length' => 255]);
    $emailRecipients->addColumn('email_type', Types::SMALLINT, ['default' => 0]);
    $emailRecipients->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('recipient_id')->create(),
    );
    $emailRecipients->addIndex(['recipient_email']);
    $emailRecipients->addIndex(['email_type']);
    $emailRecipients->addUniqueIndex(['message_id', 'recipient_email', 'email_type']);
    $emailRecipients->addForeignKeyConstraint('core_email_queue', ['message_id'], ['message_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $emailRecipients->setComment('Email Queue');

    $emailLog = $schema->createTable('core_email_log');
    $emailLog->addColumn('log_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $emailLog->addColumn('subject', Types::STRING, ['length' => 255, 'default' => '']);
    $emailLog->addColumn('email_to', Types::TEXT, ['length' => 65535]);
    $emailLog->addColumn('email_from', Types::STRING, ['length' => 255, 'default' => '']);
    $emailLog->addColumn('email_cc', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $emailLog->addColumn('email_bcc', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $emailLog->addColumn('template', Types::STRING, ['length' => 255, 'notnull' => false]);
    $emailLog->addColumn('content_type', Types::STRING, ['length' => 4, 'default' => 'html']);
    $emailLog->addColumn('email_body', Types::TEXT, ['length' => 16777215]);
    $emailLog->addColumn('status', Types::STRING, ['length' => 10, 'default' => 'sent']);
    $emailLog->addColumn('error_message', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $emailLog->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $emailLog->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('log_id')->create(),
    );
    $emailLog->addIndex(['created_at']);
    $emailLog->addIndex(['status']);
    $emailLog->setComment('Email Log');
};
