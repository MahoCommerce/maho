<?php

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
    // upgrade-1.6.0.1-1.6.0.2 widened `data` from 64k to 4G (capped at MAX_TEXT_SIZE = 2147483648 → LONGTEXT on MySQL)
    $importData->addColumn('data', Types::TEXT, ['length' => 2147483648, 'notnull' => false, 'default' => '']);
    $importData->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
    );
    $importData->setComment('Import Data Table');

    // Legacy install grafted four unique indexes and two FKs onto Mage_Catalog
    // tables (configurable product import relies on these). Keep them here so
    // ImportExport owns the additions instead of leaking them into Catalog.
    $superLink = $schema->getTable('catalog_product_super_link');
    $superLink->addUniqueIndex(['product_id', 'parent_id'], 'unq_catalog_product_super_link_product_id_parent_id');

    $superAttribute = $schema->getTable('catalog_product_super_attribute');
    $superAttribute->addUniqueIndex(['product_id', 'attribute_id'], 'unq_catalog_product_super_attribute_product_id_attribute_id');

    $superAttributePricing = $schema->getTable('catalog_product_super_attribute_pricing');
    $superAttributePricing->addUniqueIndex(
        ['product_super_attribute_id', 'value_index', 'website_id'],
        'unq_catalog_product_super_attribute_pricing_attr_value_website',
    );

    $linkAttributeInt = $schema->getTable('catalog_product_link_attribute_int');
    $linkAttributeInt->addUniqueIndex(
        ['product_link_attribute_id', 'link_id'],
        'unq_catalog_product_link_attribute_int_attr_id_link_id',
    );
    $linkAttributeInt->addForeignKeyConstraint(
        'catalog_product_link',
        ['link_id'],
        ['link_id'],
        ['onDelete' => 'CASCADE'],
        'fk_catalog_product_link_attribute_int_link',
    );
    $linkAttributeInt->addForeignKeyConstraint(
        'catalog_product_link_attribute',
        ['product_link_attribute_id'],
        ['product_link_attribute_id'],
        ['onDelete' => 'CASCADE'],
        'fk_catalog_product_link_attribute_int_attr',
    );
};
