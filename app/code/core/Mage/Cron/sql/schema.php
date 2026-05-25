<?php

/**
 * Maho
 *
 * @package    Mage_Cron
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $t = $schema->createTable('cron_schedule');

    $t->addColumn('schedule_id',  Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $t->addColumn('job_code',     Types::STRING,  ['length' => 255, 'default' => '0']);
    $t->addColumn('status',       Types::STRING,  ['length' => 7,   'default' => 'pending']);
    $t->addColumn('messages',     Types::TEXT,    ['length' => 65535, 'notnull' => false]);
    $t->addColumn('created_at',   Types::DATETIME_MUTABLE);
    $t->addColumn('scheduled_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $t->addColumn('executed_at',  Types::DATETIME_MUTABLE, ['notnull' => false]);
    $t->addColumn('finished_at',  Types::DATETIME_MUTABLE, ['notnull' => false]);

    $t->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('schedule_id')->create(),
    );
    $t->addIndex(['job_code'],               'idx_cron_schedule_job_code');
    $t->addIndex(['scheduled_at', 'status'], 'idx_cron_schedule_scheduled_at_status');

    $t->setComment('Cron Schedule');
};
