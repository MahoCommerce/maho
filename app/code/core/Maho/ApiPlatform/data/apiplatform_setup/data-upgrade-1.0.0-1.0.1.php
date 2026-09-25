<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

/**
 * The consent screen checked system/api/oauth_clients before api_connect existed.
 * Every role that had it keeps the ability to connect applications.
 *
 * @var Mage_Core_Model_Resource_Setup $this
 */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$table = $installer->getTable('admin/rule');

$roleIds = $connection->fetchCol(
    $connection->select()
        ->from($table, ['role_id'])
        ->where('resource_id = ?', 'admin/system/api/oauth_clients')
        ->where('permission = ?', 'allow'),
);

foreach ($roleIds as $roleId) {
    $connection->delete($table, ['role_id = ?' => (int) $roleId, 'resource_id = ?' => 'admin/api_connect']);
    $connection->insert($table, [
        'role_id' => (int) $roleId,
        'resource_id' => 'admin/api_connect',
        'role_type' => 'G',
        'permission' => 'allow',
    ]);
}

$installer->endSetup();
