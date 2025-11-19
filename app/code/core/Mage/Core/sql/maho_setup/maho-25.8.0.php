<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;

$installer->startSetup();

// Migrate dev/log/max_level to dev/log/min_level
$connection = $installer->getConnection();
$configTable = $installer->getTable('core/config_data');

// Check if max_level exists
$select = $connection->select()
    ->from($configTable)
    ->where('path = ?', 'dev/log/max_level');

$maxLevelConfigs = $connection->fetchAll($select);

foreach ($maxLevelConfigs as $config) {
    // Convert old Zend_Log levels (0-7) to new Monolog levels
    $oldValue = (int) $config['value'];
    $newValue = match ($oldValue) {
        0 => 600, // Emergency (was EMERG)
        1 => 550, // Alert
        2 => 500, // Critical (was CRIT)
        3 => 400, // Error (was ERR)
        4 => 300, // Warning (was WARN)
        5 => 250, // Notice
        6 => 200, // Info
        7 => 100, // Debug
        default => 100, // Default to Debug
    };

    // Insert new min_level config
    $connection->insertOnDuplicate(
        $configTable,
        [
            'scope' => $config['scope'],
            'scope_id' => $config['scope_id'],
            'path' => 'dev/log/min_level',
            'value' => $newValue,
        ],
        ['value'],
    );
}

// Delete old max_level configs
$connection->delete($configTable, ['path = ?' => 'dev/log/max_level']);

$installer->endSetup();
