<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('newsletter_subscriber')
            ->addColumn(Schema::column('subscriber_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('change_status_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('subscriber_email', Types::STRING, length: 150, notNull: false))
            ->addColumn(Schema::column('subscriber_status', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('subscriber_confirm_code', Types::STRING, length: 32, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('subscriber_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id'))
            // Leftmost prefix also serves store_id-only lookups and the store FK
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id', 'subscriber_status'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Newsletter Subscriber')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('newsletter_template')
            ->addColumn(Schema::column('template_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('template_code', Types::STRING, length: 150, notNull: false))
            ->addColumn(Schema::column('template_text', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('template_text_preprocessed', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('template_styles', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('template_type', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('template_subject', Types::STRING, length: 200, notNull: false))
            ->addColumn(Schema::column('template_sender_name', Types::STRING, length: 200, notNull: false))
            ->addColumn(Schema::column('template_sender_email', Types::STRING, length: 200, notNull: false))
            ->addColumn(Schema::column('template_actual', Types::SMALLINT, unsigned: true, notNull: false, default: 1))
            ->addColumn(Schema::column('added_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('modified_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('template_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('template_actual'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('added_at'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('modified_at'))
            ->setComment('Newsletter Template')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('newsletter_queue')
            ->addColumn(Schema::column('queue_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('template_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('newsletter_type', Types::INTEGER, notNull: false))
            ->addColumn(Schema::column('newsletter_text', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('newsletter_styles', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('newsletter_subject', Types::STRING, length: 200, notNull: false))
            ->addColumn(Schema::column('newsletter_sender_name', Types::STRING, length: 200, notNull: false))
            ->addColumn(Schema::column('newsletter_sender_email', Types::STRING, length: 200, notNull: false))
            ->addColumn(Schema::column('queue_status', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('queue_start_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('queue_finish_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('queue_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('template_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('template_id')
                    ->setUnquotedReferencedTableName('newsletter_template')
                    ->setUnquotedReferencedColumnNames('template_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Newsletter Queue')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('newsletter_queue_link')
            ->addColumn(Schema::column('queue_link_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('queue_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('subscriber_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('letter_sent_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('queue_link_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('subscriber_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('queue_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('queue_id', 'letter_sent_at'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('queue_id')
                    ->setUnquotedReferencedTableName('newsletter_queue')
                    ->setUnquotedReferencedColumnNames('queue_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('subscriber_id')
                    ->setUnquotedReferencedTableName('newsletter_subscriber')
                    ->setUnquotedReferencedColumnNames('subscriber_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Newsletter Queue Link')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('newsletter_queue_store_link')
            ->addColumn(Schema::column('queue_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('queue_id', 'store_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('queue_id')
                    ->setUnquotedReferencedTableName('newsletter_queue')
                    ->setUnquotedReferencedColumnNames('queue_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Newsletter Queue Store Link')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('newsletter_problem')
            ->addColumn(Schema::column('problem_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('subscriber_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('queue_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('problem_error_code', Types::INTEGER, unsigned: true, notNull: false, default: 0))
            ->addColumn(Schema::column('problem_error_text', Types::STRING, length: 200, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('problem_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('subscriber_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('queue_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('queue_id')
                    ->setUnquotedReferencedTableName('newsletter_queue')
                    ->setUnquotedReferencedColumnNames('queue_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('subscriber_id')
                    ->setUnquotedReferencedTableName('newsletter_subscriber')
                    ->setUnquotedReferencedColumnNames('subscriber_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Newsletter Problems')
            ->create(),
    );
};
