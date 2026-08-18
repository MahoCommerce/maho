<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $identity = $schema->createTable('social_login_identity');
    $identity->addColumn('identity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $identity->addColumn('customer_id', Types::INTEGER, ['unsigned' => true]);
    $identity->addColumn('website_id', Types::SMALLINT, ['unsigned' => true]);
    $identity->addColumn('provider', Types::STRING, ['length' => 32]);
    $identity->addColumn('provider_id', Types::STRING, ['length' => 255]);
    $identity->addColumn('provider_email', Types::STRING, ['length' => 255, 'notnull' => false]);
    $identity->addColumn('created_at', Types::DATETIME_MUTABLE);
    $identity->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('identity_id')->create(),
    );
    $identity->addUniqueIndex(['provider', 'provider_id', 'website_id']);
    $identity->addIndex(['customer_id']);
    $identity->addForeignKeyConstraint(
        'customer_entity',
        ['customer_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $identity->addForeignKeyConstraint(
        'core_website',
        ['website_id'],
        ['website_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $identity->setComment('Social Login Provider Identities');
};
