<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $assert = $schema->createTable('api_assert');
    $assert->addColumn('assert_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $assert->addColumn('assert_type', Types::STRING, ['length' => 20, 'notnull' => false]);
    $assert->addColumn('assert_data', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $assert->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('assert_id')->create(),
    );
    $assert->setComment('Api ACL Asserts');

    $session = $schema->createTable('api_session');
    $session->addColumn('user_id', Types::INTEGER, ['unsigned' => true]);
    $session->addColumn('logdate', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $session->addColumn('sessid', Types::STRING, ['length' => 40, 'notnull' => false]);
    $session->addIndex(['user_id']);
    $session->addIndex(['sessid']);
    $session->addForeignKeyConstraint(
        'api_user',
        ['user_id'],
        ['user_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $session->setComment('Api Sessions');
};
