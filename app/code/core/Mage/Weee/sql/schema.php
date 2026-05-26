<?php

/**
 * Maho
 *
 * @package    Mage_Weee
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $tax = $schema->createTable('weee_tax');
    $tax->addColumn('value_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $tax->addColumn('website_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $tax->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $tax->addColumn('country', Types::STRING, ['length' => 2, 'notnull' => false]);
    $tax->addColumn('value', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $tax->addColumn('state', Types::STRING, ['length' => 255, 'default' => '*']);
    $tax->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true]);
    $tax->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true]);
    $tax->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $tax->addIndex(['website_id'], 'idx_weee_tax_website_id');
    $tax->addIndex(['entity_id'], 'idx_weee_tax_entity_id');
    $tax->addIndex(['country'], 'idx_weee_tax_country');
    $tax->addIndex(['attribute_id'], 'idx_weee_tax_attribute_id');
    $tax->addForeignKeyConstraint('directory_country', ['country'], ['country_id'], ['onDelete' => 'CASCADE'], 'fk_weee_tax_country_directory_country');
    $tax->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onDelete' => 'CASCADE'], 'fk_weee_tax_website_core_website');
    // FKs to catalog_product_entity and eav_attribute will be added when
    // Mage_Catalog and Mage_Eav are converted to declarative schema.
    $tax->setComment('Weee Tax');

    $discount = $schema->createTable('weee_discount');
    $discount->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $discount->addColumn('website_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $discount->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $discount->addColumn('value', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $discount->addIndex(['website_id'], 'idx_weee_discount_website_id');
    $discount->addIndex(['entity_id'], 'idx_weee_discount_entity_id');
    $discount->addIndex(['customer_group_id'], 'idx_weee_discount_customer_group_id');
    $discount->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onDelete' => 'CASCADE'], 'fk_weee_discount_website_core_website');
    // FKs to customer_group and catalog_product_entity will be added when
    // Mage_Customer and Mage_Catalog are converted to declarative schema.
    $discount->setComment('Weee Discount');
};
