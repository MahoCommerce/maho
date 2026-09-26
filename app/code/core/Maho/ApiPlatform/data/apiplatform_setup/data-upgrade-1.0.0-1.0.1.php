<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

/**
 * The consent screen checked system/api/oauth_clients before api_connect existed.
 * Each role gets for api_connect the rule that decided system/api/oauth_clients.
 *
 * @var Mage_Core_Model_Resource_Setup $this
 */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$table = $installer->getTable('admin/rule');

// A parent sorts before its children, so the most specific rule of a role comes last.
$rules = $connection->fetchAll(
    $connection->select()
        ->from($table, ['role_id', 'permission'])
        ->where('resource_id IN (?)', ['admin', 'admin/system', 'admin/system/api', 'admin/system/api/oauth_clients'])
        ->order('resource_id ASC'),
);

$permissions = [];
foreach ($rules as $rule) {
    $permissions[(int) $rule['role_id']] = (string) $rule['permission'];
}

foreach ($permissions as $roleId => $permission) {
    $connection->delete($table, ['role_id = ?' => $roleId, 'resource_id = ?' => 'admin/api_connect']);
    $connection->insert($table, [
        'role_id' => $roleId,
        'resource_id' => 'admin/api_connect',
        'role_type' => 'G',
        'permission' => $permission,
    ]);
}

$installer->endSetup();
