<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

/**
 * Migrate `main`-tracker config from the pre-#877 maho_paypal/... namespace.
 * No-op for fresh installs and for case-3 legacy Mage_Paypal merchants since
 * neither has any maho_paypal/... rows.
 *
 * A blind `REPLACE(path, 'maho_paypal/', 'paypal/')` update is unsafe: when a
 * shop already holds a paypal/... row for the same scope (e.g. it re-saved the
 * config under the new namespace after #877), renaming the stale maho_paypal/...
 * row onto it violates the UNQ_CORE_CONFIG_DATA_SCOPE_SCOPE_ID_PATH unique key
 * and aborts the whole migration. So we resolve per-row: the live code reads
 * paypal/..., so an existing paypal/... row is authoritative and the colliding
 * maho_paypal/... row is a stale duplicate we drop; non-colliding rows are
 * promoted to the new namespace as before.
 *
 * @var Mage_Core_Model_Resource_Setup $this
 */
$installer = $this;
$installer->startSetup();

$conn = $installer->getConnection();
$table = $installer->getTable('core/config_data');

$rows = $conn->fetchAll(
    $conn->select()
        ->from($table, ['config_id', 'scope', 'scope_id', 'path'])
        ->where('path LIKE ?', 'paypal/%')
        ->orWhere('path LIKE ?', 'maho_paypal/%'),
);

$existing = [];
$legacy = [];
foreach ($rows as $row) {
    if (str_starts_with($row['path'], 'maho_paypal/')) {
        $legacy[] = $row;
    } else {
        $existing[$row['scope'] . '|' . $row['scope_id'] . '|' . $row['path']] = true;
    }
}

foreach ($legacy as $row) {
    $newPath = 'paypal/' . substr($row['path'], strlen('maho_paypal/'));
    $key = $row['scope'] . '|' . $row['scope_id'] . '|' . $newPath;
    if (isset($existing[$key])) {
        // Target already holds the live value; drop the stale pre-#877 duplicate.
        $conn->delete($table, ['config_id = ?' => $row['config_id']]);
    } else {
        $conn->update($table, ['path' => $newPath], ['config_id = ?' => $row['config_id']]);
    }
}

$installer->endSetup();
