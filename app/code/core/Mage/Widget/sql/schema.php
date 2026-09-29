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

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('widget')
            ->addColumn(Column::editor()->setUnquotedName('widget_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('widget_code')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('widget_type')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('parameters')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('widget_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('widget_code'))
            ->setComment('Preconfigured Widgets')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('widget_instance')
            ->addColumn(Column::editor()->setUnquotedName('instance_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('instance_type')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('package_theme')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('title')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('store_ids')->setTypeName(Types::STRING)->setLength(255)->setDefaultValue('0')->create())
            ->addColumn(Column::editor()->setUnquotedName('widget_parameters')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('sort_order')->setTypeName(Types::SMALLINT)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('instance_id')->create())
            ->setComment('Instances of Widget for Package Theme')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('widget_instance_page')
            ->addColumn(Column::editor()->setUnquotedName('page_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('instance_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('page_group')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('layout_handle')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('block_reference')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('page_for')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('entities')->setTypeName(Types::TEXT)->setLength(65535)->setNotNull(false)->create())
            ->addColumn(Column::editor()->setUnquotedName('page_template')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
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
            ->addColumn(Column::editor()->setUnquotedName('page_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addColumn(Column::editor()->setUnquotedName('layout_update_id')->setTypeName(Types::INTEGER)->setUnsigned(true)->setDefaultValue(0)->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('page_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('layout_update_id'))
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('layout_update_id', 'page_id'))
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
