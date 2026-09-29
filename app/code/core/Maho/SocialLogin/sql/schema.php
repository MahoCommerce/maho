<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
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
            ->setUnquotedName('social_login_identity')
            ->addColumn(Schema::column('identity_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('provider', Types::STRING, length: 32))
            ->addColumn(Schema::column('provider_id', Types::STRING, length: 255))
            ->addColumn(Schema::column('provider_email', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('identity_id')->create())
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('provider', 'provider_id', 'website_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_id')
                    ->setUnquotedReferencedTableName('customer_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
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
            ->setComment('Social Login Provider Identities')
            ->create(),
    );
};
