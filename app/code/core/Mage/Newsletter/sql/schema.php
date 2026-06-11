<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $subscriber = $schema->createTable('newsletter_subscriber');
    $subscriber->addColumn('subscriber_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $subscriber->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $subscriber->addColumn('change_status_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $subscriber->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $subscriber->addColumn('subscriber_email', Types::STRING, ['length' => 150, 'notnull' => false]);
    $subscriber->addColumn('subscriber_status', Types::INTEGER, ['default' => 0]);
    $subscriber->addColumn('subscriber_confirm_code', Types::STRING, ['length' => 32, 'notnull' => false]);
    $subscriber->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('subscriber_id')->create(),
    );
    $subscriber->addIndex(['customer_id']);
    $subscriber->addIndex(['store_id']);
    $subscriber->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
    );
    $subscriber->setComment('Newsletter Subscriber');

    $template = $schema->createTable('newsletter_template');
    $template->addColumn('template_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $template->addColumn('template_code', Types::STRING, ['length' => 150, 'notnull' => false]);
    $template->addColumn('template_text', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $template->addColumn('template_text_preprocessed', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $template->addColumn('template_styles', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $template->addColumn('template_type', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $template->addColumn('template_subject', Types::STRING, ['length' => 200, 'notnull' => false]);
    $template->addColumn('template_sender_name', Types::STRING, ['length' => 200, 'notnull' => false]);
    $template->addColumn('template_sender_email', Types::STRING, ['length' => 200, 'notnull' => false]);
    $template->addColumn('template_actual', Types::SMALLINT, ['unsigned' => true, 'notnull' => false, 'default' => 1]);
    $template->addColumn('added_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $template->addColumn('modified_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $template->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('template_id')->create(),
    );
    $template->addIndex(['template_actual']);
    $template->addIndex(['added_at']);
    $template->addIndex(['modified_at']);
    $template->setComment('Newsletter Template');

    $queue = $schema->createTable('newsletter_queue');
    $queue->addColumn('queue_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $queue->addColumn('template_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $queue->addColumn('newsletter_type', Types::INTEGER, ['notnull' => false]);
    $queue->addColumn('newsletter_text', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $queue->addColumn('newsletter_styles', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $queue->addColumn('newsletter_subject', Types::STRING, ['length' => 200, 'notnull' => false]);
    $queue->addColumn('newsletter_sender_name', Types::STRING, ['length' => 200, 'notnull' => false]);
    $queue->addColumn('newsletter_sender_email', Types::STRING, ['length' => 200, 'notnull' => false]);
    $queue->addColumn('queue_status', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $queue->addColumn('queue_start_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $queue->addColumn('queue_finish_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $queue->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('queue_id')->create(),
    );
    $queue->addIndex(['template_id']);
    $queue->addForeignKeyConstraint(
        'newsletter_template',
        ['template_id'],
        ['template_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $queue->setComment('Newsletter Queue');

    $queueLink = $schema->createTable('newsletter_queue_link');
    $queueLink->addColumn('queue_link_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $queueLink->addColumn('queue_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $queueLink->addColumn('subscriber_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $queueLink->addColumn('letter_sent_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $queueLink->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('queue_link_id')->create(),
    );
    $queueLink->addIndex(['subscriber_id']);
    $queueLink->addIndex(['queue_id']);
    $queueLink->addIndex(['queue_id', 'letter_sent_at']);
    $queueLink->addForeignKeyConstraint(
        'newsletter_queue',
        ['queue_id'],
        ['queue_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $queueLink->addForeignKeyConstraint(
        'newsletter_subscriber',
        ['subscriber_id'],
        ['subscriber_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $queueLink->setComment('Newsletter Queue Link');

    $queueStoreLink = $schema->createTable('newsletter_queue_store_link');
    $queueStoreLink->addColumn('queue_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $queueStoreLink->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $queueStoreLink->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('queue_id', 'store_id')->create(),
    );
    $queueStoreLink->addIndex(['store_id']);
    $queueStoreLink->addForeignKeyConstraint(
        'newsletter_queue',
        ['queue_id'],
        ['queue_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $queueStoreLink->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $queueStoreLink->setComment('Newsletter Queue Store Link');

    $problem = $schema->createTable('newsletter_problem');
    $problem->addColumn('problem_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $problem->addColumn('subscriber_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $problem->addColumn('queue_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $problem->addColumn('problem_error_code', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'default' => 0]);
    $problem->addColumn('problem_error_text', Types::STRING, ['length' => 200, 'notnull' => false]);
    $problem->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('problem_id')->create(),
    );
    $problem->addIndex(['subscriber_id']);
    $problem->addIndex(['queue_id']);
    $problem->addForeignKeyConstraint(
        'newsletter_queue',
        ['queue_id'],
        ['queue_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $problem->addForeignKeyConstraint(
        'newsletter_subscriber',
        ['subscriber_id'],
        ['subscriber_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $problem->setComment('Newsletter Problems');
};
