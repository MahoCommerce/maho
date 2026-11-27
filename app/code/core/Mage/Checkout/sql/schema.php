<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
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
            ->setUnquotedName('checkout_agreement')
            ->addColumn(Schema::column('agreement_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('name', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('content', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('content_height', Types::STRING, length: 25, notNull: false))
            ->addColumn(Schema::column('checkbox_text', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('is_active', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('is_html', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('position', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('version', Types::INTEGER, unsigned: true, default: 1, comment: 'Number of the current text, see checkout_agreement_version'))
            ->addColumn(Schema::column('show_on_checkout', Types::SMALLINT, default: 1))
            ->addColumn(Schema::column('show_on_registration', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('requires_reconsent', Types::SMALLINT, default: 0, comment: 'A new version needs a new acceptance'))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('agreement_id')->create())
            ->setComment('Checkout Agreement')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('checkout_agreement_store')
            ->addColumn(Schema::column('agreement_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('agreement_id', 'store_id')
                    ->create(),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('agreement_id')
                    ->setUnquotedReferencedTableName('checkout_agreement')
                    ->setUnquotedReferencedColumnNames('agreement_id')
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
            ->setComment('Checkout Agreement Store')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('checkout_agreement_version')
            ->addColumn(Schema::column('version_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('agreement_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('version', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('name', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('content', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('checkbox_text', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('is_html', Types::SMALLINT, default: 0))
            ->addColumn(Schema::column('created_at', Types::DATETIME_IMMUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('version_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('agreement_id', 'version'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('agreement_id')
                    ->setUnquotedReferencedTableName('checkout_agreement')
                    ->setUnquotedReferencedColumnNames('agreement_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Text of each version of a checkout agreement, kept after the agreement is deleted')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('checkout_agreement_acceptance')
            ->addColumn(Schema::column('acceptance_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('version_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('agreement_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('customer_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('email', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('order_id', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, notNull: false))
            ->addColumn(Schema::column('context', Types::STRING, length: 32))
            ->addColumn(Schema::column('remote_ip', Types::STRING, length: 45, notNull: false))
            ->addColumn(Schema::column('user_agent', Types::STRING, length: 512, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_IMMUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('acceptance_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('agreement_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('customer_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('email'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('order_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('version_id')
                    ->setUnquotedReferencedTableName('checkout_agreement_version')
                    ->setUnquotedReferencedColumnNames('version_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::RESTRICT)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('agreement_id')
                    ->setUnquotedReferencedTableName('checkout_agreement')
                    ->setUnquotedReferencedColumnNames('agreement_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('customer_id')
                    ->setUnquotedReferencedTableName('customer_entity')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('order_id')
                    ->setUnquotedReferencedTableName('sales_flat_order')
                    ->setUnquotedReferencedColumnNames('entity_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('store_id')
                    ->setUnquotedReferencedTableName('core_store')
                    ->setUnquotedReferencedColumnNames('store_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::SET_NULL)
                    ->create(),
            )
            ->setComment('Proof that a customer accepted a version of a checkout agreement')
            ->create(),
    );
};
