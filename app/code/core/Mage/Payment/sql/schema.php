<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    // payment_restriction created by maho-25.5.0; ON UPDATE CURRENT_TIMESTAMP on
    // updated_at removed by maho-26.5.0 (now managed in PHP via _beforeSave()).
    $restriction = $schema->createTable('payment_restriction');
    $restriction->addColumn('restriction_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $restriction->addColumn('name', Types::STRING, ['length' => 255]);
    $restriction->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $restriction->addColumn('status', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $restriction->addColumn('payment_methods', Types::TEXT, ['length' => 65535]);
    $restriction->addColumn('customer_groups', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $restriction->addColumn('websites', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $restriction->addColumn('from_date', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $restriction->addColumn('to_date', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $restriction->addColumn('conditions_serialized', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $restriction->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $restriction->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $restriction->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('restriction_id')->create(),
    );
    $restriction->addIndex(['status'], 'idx_payment_restriction_status');
    $restriction->addIndex(['from_date'], 'idx_payment_restriction_from_date');
    $restriction->addIndex(['to_date'], 'idx_payment_restriction_to_date');
    $restriction->setComment('Payment Method Restrictions');
};
