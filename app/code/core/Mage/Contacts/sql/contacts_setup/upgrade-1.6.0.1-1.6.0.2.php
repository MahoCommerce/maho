<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Contacts
 */

/**
 * The contact-form rate limit moved into the centralized
 * `system/rate_limit/contact` so every endpoint with rate limiting lives
 * in one place. Migrate any operator-customised value across; if the
 * destination already has a non-default value, the operator's existing
 * `system/rate_limit/contact` wins (we don't overwrite).
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$table = $installer->getTable('core_config_data');

$rows = $connection->fetchAll(
    $connection->select()
        ->from($table, ['scope', 'scope_id', 'value'])
        ->where('path = ?', 'contacts/api/rate_limit'),
);

foreach ($rows as $row) {
    $existing = $connection->fetchOne(
        $connection->select()
            ->from($table, 'value')
            ->where('path = ?', 'system/rate_limit/contact')
            ->where('scope = ?', $row['scope'])
            ->where('scope_id = ?', (int) $row['scope_id']),
    );

    if ($existing === false || $existing === null || $existing === '') {
        $connection->insertOnDuplicate($table, [
            'scope' => $row['scope'],
            'scope_id' => (int) $row['scope_id'],
            'path' => 'system/rate_limit/contact',
            'value' => $row['value'],
        ]);
    }
}

$connection->delete(
    $table,
    $connection->prepareSqlCondition('path', ['eq' => 'contacts/api/rate_limit']),
);

$installer->endSetup();
