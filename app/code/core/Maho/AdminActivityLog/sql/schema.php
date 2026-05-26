<?php

/**
 * Maho
 *
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $activity = $schema->createTable('adminactivitylog_activity');
    $activity->addColumn('activity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $activity->addColumn('action_group_id', Types::STRING, ['length' => 64, 'notnull' => false]);
    $activity->addColumn('user_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $activity->addColumn('username', Types::STRING, ['length' => 40, 'notnull' => false]);
    $activity->addColumn('action_type', Types::STRING, ['length' => 50]);
    $activity->addColumn('entity_type', Types::STRING, ['length' => 255, 'notnull' => false]);
    $activity->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $activity->addColumn('old_data', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $activity->addColumn('new_data', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $activity->addColumn('ip_address', Types::STRING, ['length' => 45, 'notnull' => false]);
    $activity->addColumn('user_agent', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $activity->addColumn('request_url', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $activity->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $activity->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('activity_id')->create(),
    );
    $activity->addIndex(['user_id'], 'idx_adminactivitylog_activity_user_id');
    $activity->addIndex(['action_group_id'], 'idx_adminactivitylog_activity_action_group_id');
    $activity->addIndex(['action_type'], 'idx_adminactivitylog_activity_action_type');
    $activity->addIndex(['entity_type'], 'idx_adminactivitylog_activity_entity_type');
    $activity->addIndex(['entity_id'], 'idx_adminactivitylog_activity_entity_id');
    $activity->addIndex(['created_at'], 'idx_adminactivitylog_activity_created_at');
    $activity->addForeignKeyConstraint(
        'admin_user',
        ['user_id'],
        ['user_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
        'fk_adminactivitylog_activity_user',
    );
    $activity->setComment('Admin Activity Log Table');

    $login = $schema->createTable('adminactivitylog_login');
    $login->addColumn('login_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $login->addColumn('user_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $login->addColumn('username', Types::STRING, ['length' => 40]);
    $login->addColumn('type', Types::STRING, ['length' => 20]);
    $login->addColumn('ip_address', Types::STRING, ['length' => 45, 'notnull' => false]);
    $login->addColumn('user_agent', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $login->addColumn('failure_reason', Types::STRING, ['length' => 255, 'notnull' => false]);
    $login->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $login->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('login_id')->create(),
    );
    $login->addIndex(['user_id'], 'idx_adminactivitylog_login_user_id');
    $login->addIndex(['username'], 'idx_adminactivitylog_login_username');
    $login->addIndex(['type'], 'idx_adminactivitylog_login_type');
    $login->addIndex(['created_at'], 'idx_adminactivitylog_login_created_at');
    $login->addForeignKeyConstraint(
        'admin_user',
        ['user_id'],
        ['user_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'],
        'fk_adminactivitylog_login_user',
    );
    $login->setComment('Admin Login Activity Log Table');
};
