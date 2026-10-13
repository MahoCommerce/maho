<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
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
            ->setUnquotedName('core_resource')
            ->addColumn(Schema::column('code', Types::STRING, length: 50))
            ->addColumn(Schema::column('version', Types::STRING, length: 50, notNull: false))
            ->addColumn(Schema::column('data_version', Types::STRING, length: 50, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('code')->create())
            ->setComment('Resources')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_website')
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('code', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('name', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('sort_order', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('default_group_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_default', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('website_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('code'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('sort_order'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('default_group_id'))
            ->setComment('Websites')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_store_group')
            ->addColumn(Schema::column('group_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('name', Types::STRING, length: 255))
            ->addColumn(Schema::column('root_category_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('default_store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('group_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('default_store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Store Groups')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_store')
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('code', Types::STRING, length: 32, notNull: false))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('group_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('name', Types::STRING, length: 255))
            ->addColumn(Schema::column('sort_order', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('store_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('code'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('website_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('is_active', 'sort_order'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('group_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('group_id')
                    ->setUnquotedReferencedTableName('core_store_group')
                    ->setUnquotedReferencedColumnNames('group_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('website_id')
                    ->setUnquotedReferencedTableName('core_website')
                    ->setUnquotedReferencedColumnNames('website_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Stores')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_config_data')
            ->addColumn(Schema::column('config_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('scope', Types::STRING, length: 8, default: 'default'))
            ->addColumn(Schema::column('scope_id', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('path', Types::STRING, length: 255, default: 'general'))
            ->addColumn(Schema::column('value', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('config_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('scope', 'scope_id', 'path'))
            ->setComment('Config Data')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_email_template')
            ->addColumn(Schema::column('template_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('template_code', Types::STRING, length: 150))
            ->addColumn(Schema::column('template_text', Types::TEXT, length: 65535))
            ->addColumn(Schema::column('template_styles', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('template_type', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('template_subject', Types::STRING, length: 200))
            ->addColumn(Schema::column('template_sender_name', Types::STRING, length: 200, notNull: false))
            ->addColumn(Schema::column('template_sender_email', Types::STRING, length: 200, notNull: false))
            ->addColumn(Schema::column('added_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('modified_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('orig_template_code', Types::STRING, length: 200, notNull: false))
            ->addColumn(Schema::column('orig_template_variables', Types::TEXT, length: 65535, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('template_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('template_code'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('added_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('modified_at'))
            ->setComment('Email Templates')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_layout_update')
            ->addColumn(Schema::column('layout_update_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('handle', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('xml', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('sort_order', Types::SMALLINT, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('layout_update_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('handle'))
            ->setComment('Layout Updates')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_layout_link')
            ->addColumn(Schema::column('layout_link_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('area', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('package', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('theme', Types::STRING, length: 64, notNull: false))
            ->addColumn(Schema::column('layout_update_id', Types::INTEGER, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('layout_link_id')
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('store_id', 'package', 'theme', 'layout_update_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('layout_update_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('layout_update_id')
                    ->setUnquotedReferencedTableName('core_layout_update')
                    ->setUnquotedReferencedColumnNames('layout_update_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Layout Link')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_session')
            ->addColumn(Schema::column('session_id', Types::STRING, length: 255))
            ->addColumn(Schema::column('session_expires', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('session_data', Types::BLOB, length: 2097152))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('session_id')->create())
            ->setComment('Database Sessions Storage')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_translate')
            ->addColumn(Schema::column('key_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('string', Types::STRING, length: 255, default: 'Translate String'))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('translate', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('locale', Types::STRING, length: 20, default: 'en_US'))
            ->addColumn(Schema::column('crc_string', Types::BIGINT, default: crc32('Translate String')))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('key_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('store_id', 'locale', 'crc_string', 'string'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Translations')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_url_rewrite')
            ->addColumn(Schema::column('url_rewrite_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('id_path', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('request_path', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('target_path', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('is_system', Types::SMALLINT, unsigned: true, notNull: false, default: 1))
            ->addColumn(Schema::column('options', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('description', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('url_rewrite_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('request_path', 'store_id'))
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('id_path', 'is_system', 'store_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('target_path', 'store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('id_path'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Url Rewrites')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_url_gone')
            ->addColumn(Schema::column('gone_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('request_path', Types::STRING, length: 255))
            ->addColumn(Schema::column('entity_type', Types::STRING, length: 32))
            ->addColumn(Schema::column('deleted_at', Types::DATETIME_MUTABLE))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('gone_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('request_path', 'store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('deleted_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Gone Urls')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('design_change')
            ->addColumn(Schema::column('design_change_id', Types::INTEGER, autoincrement: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('design', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('date_from', Types::DATE_MUTABLE, notNull: false))
            ->addColumn(Schema::column('date_to', Types::DATE_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('design_change_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Design Changes')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_variable')
            ->addColumn(Schema::column('variable_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('code', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('name', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('variable_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('code'))
            ->setComment('Variables')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_variable_value')
            ->addColumn(Schema::column('value_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('variable_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('plain_value', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('html_value', Types::TEXT, length: 65535, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('variable_id', 'store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('variable_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('variable_id')
                    ->setUnquotedReferencedTableName('core_variable')
                    ->setUnquotedReferencedColumnNames('variable_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Variable Value')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_cache')
            ->addColumn(Schema::column('id', Types::STRING, length: 200))
            ->addColumn(Schema::column('data', Types::BLOB, length: 2097152, notNull: false))
            ->addColumn(Schema::column('create_time', Types::INTEGER, notNull: false))
            ->addColumn(Schema::column('update_time', Types::INTEGER, notNull: false))
            ->addColumn(Schema::column('expire_time', Types::INTEGER, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('expire_time'))
            ->setComment('Caches')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_cache_tag')
            ->addColumn(Schema::column('tag', Types::STRING, length: 100))
            ->addColumn(Schema::column('cache_id', Types::STRING, length: 200))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('tag', 'cache_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('cache_id'))
            ->setComment('Tag Caches')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_cache_option')
            ->addColumn(Schema::column('code', Types::STRING, length: 32))
            ->addColumn(Schema::column('value', Types::SMALLINT, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('code')->create())
            ->setComment('Cache Options')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_flag')
            ->addColumn(Schema::column('flag_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('flag_code', Types::STRING, length: 255))
            ->addColumn(Schema::column('state', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('flag_data', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('last_update', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('flag_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('last_update'))
            ->setComment('Flag')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_email_queue')
            ->addColumn(Schema::column('message_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('entity_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('entity_type', Types::STRING, length: 128, notNull: false))
            ->addColumn(Schema::column('event_type', Types::STRING, length: 128, notNull: false))
            ->addColumn(Schema::column('message_body_hash', Types::STRING, length: 64))
            ->addColumn(Schema::column('message_body', Types::TEXT, length: 1048576))
            ->addColumn(Schema::column('message_parameters', Types::TEXT, length: 65535))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('processed_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('message_id')->create())
            ->addIndex(
                Index::editor()
                    ->setUnquotedColumnNames('entity_id', 'entity_type', 'event_type', 'message_body_hash'),
            )
            ->setComment('Email Queue')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_email_queue_recipients')
            ->addColumn(Schema::column('recipient_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('message_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('recipient_email', Types::STRING, length: 128))
            ->addColumn(Schema::column('recipient_name', Types::STRING, length: 255))
            ->addColumn(Schema::column('email_type', Types::SMALLINT, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('recipient_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('recipient_email'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('email_type'))
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('message_id', 'recipient_email', 'email_type'),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('message_id')
                    ->setUnquotedReferencedTableName('core_email_queue')
                    ->setUnquotedReferencedColumnNames('message_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Email Queue')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('core_email_log')
            ->addColumn(Schema::column('log_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('subject', Types::STRING, length: 255, default: ''))
            ->addColumn(Schema::column('email_to', Types::TEXT, length: 65535))
            ->addColumn(Schema::column('email_from', Types::STRING, length: 255, default: ''))
            ->addColumn(Schema::column('email_cc', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('email_bcc', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('template', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('content_type', Types::STRING, length: 4, default: 'html'))
            ->addColumn(Schema::column('email_body', Types::TEXT, length: 16777215))
            ->addColumn(Schema::column('status', Types::STRING, length: 10, default: 'sent'))
            ->addColumn(Schema::column('error_message', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('log_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('created_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('status'))
            ->setComment('Email Log')
            ->create(),
    );
};
