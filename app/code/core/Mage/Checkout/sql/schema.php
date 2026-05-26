<?php

/**
 * Maho
 *
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $agreement = $schema->createTable('checkout_agreement');
    $agreement->addColumn('agreement_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $agreement->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
    $agreement->addColumn('content', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $agreement->addColumn('content_height', Types::STRING, ['length' => 25, 'notnull' => false]);
    $agreement->addColumn('checkbox_text', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $agreement->addColumn('is_active', Types::SMALLINT, ['default' => 0]);
    $agreement->addColumn('is_html', Types::SMALLINT, ['default' => 0]);
    // Added by upgrade-1.6.0.0-1.6.0.1
    $agreement->addColumn('position', Types::SMALLINT, ['default' => 0]);
    $agreement->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('agreement_id')->create(),
    );
    $agreement->setComment('Checkout Agreement');

    $agreementStore = $schema->createTable('checkout_agreement_store');
    $agreementStore->addColumn('agreement_id', Types::INTEGER, ['unsigned' => true]);
    $agreementStore->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $agreementStore->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('agreement_id', 'store_id')->create(),
    );
    $agreementStore->addIndex(['store_id'], 'idx_checkout_agreement_store_store_id');
    $agreementStore->addForeignKeyConstraint(
        'checkout_agreement',
        ['agreement_id'],
        ['agreement_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_checkout_agreement_store_agreement',
    );
    $agreementStore->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_checkout_agreement_store_store',
    );
    $agreementStore->setComment('Checkout Agreement Store');
};
