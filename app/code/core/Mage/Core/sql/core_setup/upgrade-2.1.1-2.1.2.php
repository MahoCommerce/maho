<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// The Tailwind skin now lives in the base package and the former base stylesheets
// moved to the legacy package. A store that never chose a package rendered base, so
// it keeps its look through an explicit legacy row; a fresh install gets the new skin.
// The maho package that carried the new skin before the move is gone, so a row that
// names it points at base now.
$connection = $installer->getConnection();
$table = $installer->getTable('core/config_data');
$path = 'design/package/name';

if (Mage::isInstalled()) {
    $hasDefaultRow = (bool) $connection->fetchOne(
        $connection->select()->from($table, 'config_id')->where('path = ?', $path)->where('scope = ?', 'default'),
    );
    if (!$hasDefaultRow) {
        $installer->setConfigData($path, 'legacy');
    }
}

$connection->update($table, ['value' => 'base'], ['path = ?' => $path, 'value = ?' => 'maho']);

$installer->endSetup();
