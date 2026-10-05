<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Widget
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
            ->setUnquotedName('widget')
            ->addColumn(Schema::column('widget_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('widget_code', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('widget_type', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('parameters', Types::TEXT, length: 65535, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('widget_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('widget_code'))
            ->setComment('Preconfigured Widgets')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('widget_instance')
            ->addColumn(Schema::column('instance_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('instance_type', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('package_theme', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('title', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('store_ids', Types::STRING, length: 255, default: '0'))
            ->addColumn(Schema::column('widget_parameters', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('sort_order', Types::SMALLINT, unsigned: true, default: 0))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('instance_id')->create())
            ->setComment('Instances of Widget for Package Theme')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('widget_instance_page')
            ->addColumn(Schema::column('page_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('instance_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('page_group', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('layout_handle', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('block_reference', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('page_for', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('entities', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('page_template', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('page_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('instance_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('instance_id')
                    ->setUnquotedReferencedTableName('widget_instance')
                    ->setUnquotedReferencedColumnNames('instance_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Instance of Widget on Page')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('widget_instance_page_layout')
            ->addColumn(Schema::column('page_id', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('layout_update_id', Types::INTEGER, unsigned: true, default: 0))
            ->addIndex(Index::editor()->setUnquotedColumnNames('page_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('layout_update_id'))
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('layout_update_id', 'page_id'),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('page_id')
                    ->setUnquotedReferencedTableName('widget_instance_page')
                    ->setUnquotedReferencedColumnNames('page_id')
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
            ->setComment('Layout updates')
            ->create(),
    );
};
