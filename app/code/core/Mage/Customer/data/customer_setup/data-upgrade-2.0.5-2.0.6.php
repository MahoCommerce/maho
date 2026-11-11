<?php

/**
 * Copy the magic link limits of a store to their new settings under system/rate_limit.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

/** @var Mage_Customer_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$table = $installer->getTable('core/config_data');
$paths = [
    'customer/login/magic_link_rate_limit_email' => 'system/rate_limit/magic_link_email',
    'customer/login/magic_link_rate_limit_ip' => 'system/rate_limit/magic_link_ip',
];

foreach ($paths as $oldPath => $newPath) {
    $rows = $connection->fetchAll(
        $connection->select()->from($table, ['scope', 'scope_id', 'value'])->where('path = ?', $oldPath),
    );
    foreach ($rows as $row) {
        // The old setting used its default for 0. The new setting turns the limit off for 0
        if ((int) $row['value'] <= 0) {
            continue;
        }
        $exists = $connection->fetchOne(
            $connection->select()
                ->from($table, ['config_id'])
                ->where('path = ?', $newPath)
                ->where('scope = ?', $row['scope'])
                ->where('scope_id = ?', (int) $row['scope_id']),
        );
        if ($exists === false) {
            $connection->insert($table, [
                'scope' => $row['scope'],
                'scope_id' => (int) $row['scope_id'],
                'path' => $newPath,
                'value' => (string) (int) $row['value'],
            ]);
        }
    }
}

$installer->endSetup();
