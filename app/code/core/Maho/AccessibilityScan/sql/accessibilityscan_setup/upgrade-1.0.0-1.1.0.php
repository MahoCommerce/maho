<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// The Node.js and npm paths now belong to the shared browser runtime under
// system/browser. Carry a value a store set over, unless the shared field
// already holds one, then drop the old rows.
$connection = $installer->getConnection();
$table = $installer->getTable('core/config_data');

foreach (['node_path', 'npm_path'] as $field) {
    $oldPath = "accessibilityscan/advanced/$field";
    $newPath = "system/browser/$field";

    $value = $connection->fetchOne(
        $connection->select()->from($table, 'value')->where('path = ?', $oldPath)->where('scope = ?', 'default'),
    );
    $hasNew = (bool) $connection->fetchOne(
        $connection->select()->from($table, 'config_id')->where('path = ?', $newPath)->where('scope = ?', 'default'),
    );
    if ($value !== false && $value !== null && trim((string) $value) !== '' && !$hasNew) {
        $installer->setConfigData($newPath, $value);
    }
    $installer->deleteConfigData($oldPath);
}

$installer->endSetup();
