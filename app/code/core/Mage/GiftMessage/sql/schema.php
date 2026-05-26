<?php

/**
 * Maho
 *
 * @package    Mage_GiftMessage
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $message = $schema->createTable('gift_message');
    $message->addColumn('gift_message_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $message->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $message->addColumn('sender', Types::STRING, ['length' => 255, 'notnull' => false]);
    $message->addColumn('recipient', Types::STRING, ['length' => 255, 'notnull' => false]);
    $message->addColumn('message', Types::TEXT, ['length' => 65535, 'notnull' => false]);

    $message->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('gift_message_id')->create(),
    );

    $message->setComment('Gift Message');
};
