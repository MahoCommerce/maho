<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Clear legacy zero-date sentinels left by the old Magento1/OpenMage schema; these columns are now nullable.
$columns = [
    'catalog_product_entity' => ['created_at', 'updated_at'],
    'catalog_category_entity' => ['created_at', 'updated_at'],
    'catalog_product_entity_datetime' => ['value'],
    'catalog_category_entity_datetime' => ['value'],
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
