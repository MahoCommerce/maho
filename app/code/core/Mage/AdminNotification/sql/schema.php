<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_AdminNotification
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $t = $schema->createTable('adminnotification_inbox');

    $t->addColumn('notification_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $t->addColumn('severity', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $t->addColumn('date_added', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $t->addColumn('title', Types::STRING, ['length' => 255]);
    $t->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $t->addColumn('url', Types::STRING, ['length' => 255, 'notnull' => false]);
    $t->addColumn('is_read', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $t->addColumn('is_remove', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);

    $t->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('notification_id')->create(),
    );
    $t->addIndex(['severity']);
    $t->addIndex(['is_read']);
    $t->addIndex(['is_remove']);

    $t->setComment('Adminnotification Inbox');
};
