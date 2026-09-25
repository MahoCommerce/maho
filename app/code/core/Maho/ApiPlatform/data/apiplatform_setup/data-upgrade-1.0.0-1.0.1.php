<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

/**
 * The consent screen checked system/api/oauth_clients before api_connect existed.
 * Every role that had it keeps the ability to connect applications. Every other
 * restricted role gets a deny rule, because a role without a rule for api_connect
 * gets the allow rule of its parent resource "admin".
 *
 * @var Mage_Core_Model_Resource_Setup $this
 */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$table = $installer->getTable('admin/rule');

$roleIdsOf = fn(string $resourceId): array => array_map(intval(...), $connection->fetchCol(
    $connection->select()
        ->distinct()
        ->from($table, ['role_id'])
        ->where('resource_id = ?', $resourceId)
        ->where('permission = ?', 'allow'),
));

$allowedRoleIds = $roleIdsOf('admin/system/api/oauth_clients');
$restrictedRoleIds = array_diff($roleIdsOf('admin'), $roleIdsOf('all'));

foreach (array_unique([...$allowedRoleIds, ...$restrictedRoleIds]) as $roleId) {
    $connection->delete($table, ['role_id = ?' => $roleId, 'resource_id = ?' => 'admin/api_connect']);
    $connection->insert($table, [
        'role_id' => $roleId,
        'resource_id' => 'admin/api_connect',
        'role_type' => 'G',
        'permission' => in_array($roleId, $allowedRoleIds, true) ? 'allow' : 'deny',
    ]);
}

$installer->endSetup();
