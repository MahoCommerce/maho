<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Fix admin_user.created column: remove legacy MySQL ON UPDATE CURRENT_TIMESTAMP.
 *
 * The original install DDL (install-1.6.0.0.php) declared the `created` column as
 * TIMESTAMP NOT NULL without an explicit default. On MySQL servers running with
 * `explicit_defaults_for_timestamp = OFF` (the pre-8.0 default), MySQL silently
 * applied its legacy "first TIMESTAMP NOT NULL column in a table" rule and
 * auto-added `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
 *
 * Effect: every time an admin user row was updated (e.g. on login via
 * Mage_Admin_Model_Resource_User::recordLogin()), MySQL auto-bumped `created`
 * to NOW(), destroying the record of when the user was actually created.
 *
 * This is a classic Magento-1/OpenMage inheritance bug. It breaks security
 * monitoring use cases that depend on a stable account-creation timestamp
 * (password rotation policies, dormant-account detection, audit trails).
 *
 * Fix: explicitly redefine the column with DEFAULT CURRENT_TIMESTAMP only
 * (no ON UPDATE). Existing row values are preserved — MODIFY COLUMN only
 * changes the column metadata, not the stored data.
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->modifyColumn(
    $installer->getTable('admin/user'),
    'created',
    [
        'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
        'comment'  => 'User Created Time',
    ],
);

$installer->endSetup();
