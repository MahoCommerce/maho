<?php

/**
 * Copy each stored value of an old rate limit setting to its setting under system/rate_limit, in each scope.
 * A scope that has a value at the new path keeps it.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Contacts
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$table = $installer->getTable('core_config_data');
$paths = [
    'contacts/abuse/ip_rate_limit_per_hour' => 'system/rate_limit/contact_ip',
    'contacts/abuse/recipient_rate_limit_per_hour' => 'system/rate_limit/contact_auto_reply',
];

foreach ($paths as $oldPath => $newPath) {
    $rows = $connection->fetchAll(
        $connection->select()
            ->from($table, ['scope', 'scope_id', 'value'])
            ->where('path = ?', $oldPath),
    );
    foreach ($rows as $row) {
        $exists = $connection->fetchOne(
            $connection->select()
                ->from($table, ['config_id'])
                ->where('path = ?', $newPath)
                ->where('scope = ?', $row['scope'])
                ->where('scope_id = ?', (int) $row['scope_id']),
        );
        if (!$exists) {
            $connection->insert($table, [
                'scope' => $row['scope'],
                'scope_id' => (int) $row['scope_id'],
                'path' => $newPath,
                'value' => $row['value'],
            ]);
        }
    }
}

$installer->endSetup();
