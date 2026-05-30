<?php

/**
 * Maho
 *
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;

// Postgres cannot store the legacy zero-date sentinel, so there is nothing to clean.
if ($installer->getConnection() instanceof \Maho\Db\Adapter\Pdo\Pgsql) {
    return;
}

$installer->startSetup();

// Clear legacy zero-date sentinels left by the old Magento1/OpenMage schema; these columns are now nullable.
$columns = [
    'cms_block' => ['creation_time', 'update_time'],
    'cms_page' => ['creation_time', 'update_time', 'custom_theme_from', 'custom_theme_to'],
];

$connection = $installer->getConnection();
foreach ($columns as $table => $tableColumns) {
    if (!$connection->isTableExists($table)) {
        continue;
    }
    foreach ($tableColumns as $column) {
        $connection->update($table, [$column => null], "`{$column}` LIKE '0000-00-00%'");
    }
}

$installer->endSetup();
