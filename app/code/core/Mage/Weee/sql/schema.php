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
    $tax->addIndex(['website_id']);
    $tax->addIndex(['entity_id']);
    $tax->addIndex(['country']);
    $tax->addIndex(['attribute_id']);
    $tax->addForeignKeyConstraint('directory_country', ['country'], ['country_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $tax->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $tax->addForeignKeyConstraint('catalog_product_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $tax->addForeignKeyConstraint('eav_attribute', ['attribute_id'], ['attribute_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $tax->setComment('Weee Tax');

    $discount = $schema->createTable('weee_discount');
    $discount->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $discount->addColumn('website_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $discount->addColumn('customer_group_id', Types::SMALLINT, ['unsigned' => true]);
    $discount->addColumn('value', Types::DECIMAL, ['precision' => 12, 'scale' => 4, 'default' => '0.0000']);
    $discount->addIndex(['website_id']);
    $discount->addIndex(['entity_id']);
    $discount->addIndex(['customer_group_id']);
    $discount->addForeignKeyConstraint('core_website', ['website_id'], ['website_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $discount->addForeignKeyConstraint('customer_group', ['customer_group_id'], ['customer_group_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $discount->addForeignKeyConstraint('catalog_product_entity', ['entity_id'], ['entity_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    $discount->setComment('Weee Discount');
};
