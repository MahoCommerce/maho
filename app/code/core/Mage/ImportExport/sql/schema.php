<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
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
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Types\Types;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('importexport_importdata')
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setUnsigned(true)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('entity')
                    ->setTypeName(Types::STRING)
                    ->setLength(50)
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('behavior')
                    ->setTypeName(Types::STRING)
                    ->setLength(10)
                    ->setDefaultValue('append')
                    ->create(),
            )
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('data')
                    ->setTypeName(Types::TEXT)
                    ->setLength(2147483648)
                    ->setNotNull(false)
                    ->setDefaultValue('')
                    ->create(),
            )
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setComment('Import Data Table')
            ->create(),
    );

    // ImportExport grafts four unique indexes and two FKs onto Mage_Catalog tables (configurable product import relies on these), owned here rather than in Catalog.
    $schema->modifyTableByUnquotedName('catalog_product_super_link', static function (TableEditor $superLink): void {
        $superLink->addIndex(
            Index::editor()
                ->setType(IndexType::UNIQUE)
                ->setUnquotedColumnNames('product_id', 'parent_id'),
        );
    });

    $schema->modifyTableByUnquotedName('catalog_product_super_attribute', static function (TableEditor $superAttribute): void {
        $superAttribute->addIndex(
            Index::editor()
                ->setType(IndexType::UNIQUE)
                ->setUnquotedColumnNames('product_id', 'attribute_id'),
        );
    });

    $schema->modifyTableByUnquotedName('catalog_product_super_attribute_pricing', static function (TableEditor $superAttributePricing): void {
        $superAttributePricing->addIndex(
            Index::editor()
                ->setType(IndexType::UNIQUE)
                ->setUnquotedColumnNames('product_super_attribute_id', 'value_index', 'website_id'),
        );
    });

    $schema->modifyTableByUnquotedName('catalog_product_link_attribute_int', static function (TableEditor $linkAttributeInt): void {
        $linkAttributeInt->addIndex(
            Index::editor()
                ->setType(IndexType::UNIQUE)
                ->setUnquotedColumnNames('product_link_attribute_id', 'link_id'),
        );
        $linkAttributeInt->addForeignKeyConstraint(
            ForeignKeyConstraint::editor()
                ->setUnquotedReferencingColumnNames('link_id')
                ->setUnquotedReferencedTableName('catalog_product_link')
                ->setUnquotedReferencedColumnNames('link_id')
                ->setOnUpdateAction(ReferentialAction::CASCADE)
                ->setOnDeleteAction(ReferentialAction::CASCADE)
                ->create(),
        );
        $linkAttributeInt->addForeignKeyConstraint(
            ForeignKeyConstraint::editor()
                ->setUnquotedReferencingColumnNames('product_link_attribute_id')
                ->setUnquotedReferencedTableName('catalog_product_link_attribute')
                ->setUnquotedReferencedColumnNames('product_link_attribute_id')
                ->setOnUpdateAction(ReferentialAction::CASCADE)
                ->setOnDeleteAction(ReferentialAction::CASCADE)
                ->create(),
        );
    });
};
