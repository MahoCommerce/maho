<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $importData = $schema->createTable('importexport_importdata');
    $importData->addColumn('id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $importData->addColumn('entity', Types::STRING, ['length' => 50]);
    $importData->addColumn('behavior', Types::STRING, ['length' => 10, 'default' => 'append']);
    $importData->addColumn('data', Types::TEXT, ['length' => 2147483648, 'notnull' => false, 'default' => '']);
    $importData->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
    );
    $importData->setComment('Import Data Table');

    // ImportExport grafts four unique indexes and two FKs onto Mage_Catalog tables (configurable product import relies on these), owned here rather than in Catalog.
    $superLink = $schema->getTable('catalog_product_super_link');
    $superLink->addUniqueIndex(['product_id', 'parent_id']);

    $superAttribute = $schema->getTable('catalog_product_super_attribute');
    $superAttribute->addUniqueIndex(['product_id', 'attribute_id']);

    $superAttributePricing = $schema->getTable('catalog_product_super_attribute_pricing');
    $superAttributePricing->addUniqueIndex(
        ['product_super_attribute_id', 'value_index', 'website_id'],
    );

    $linkAttributeInt = $schema->getTable('catalog_product_link_attribute_int');
    $linkAttributeInt->addUniqueIndex(
        ['product_link_attribute_id', 'link_id'],
    );
    $linkAttributeInt->addForeignKeyConstraint(
        'catalog_product_link',
        ['link_id'],
        ['link_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $linkAttributeInt->addForeignKeyConstraint(
        'catalog_product_link_attribute',
        ['product_link_attribute_id'],
        ['product_link_attribute_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
};
