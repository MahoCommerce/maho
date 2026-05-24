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

// Revert the 2012-era MEMORY-engine optimization on indexer `_tmp` tables.
// On MySQL 8.4+ `enforce_gtid_consistency=ON` is the default, which forbids
// mixing writes to non-transactional (MEMORY) and transactional (InnoDB)
// tables in the same transaction. The price indexer runs inside the order
// transaction during checkout, so the MEMORY tables break checkout out of
// the box on stock MySQL 8.4+. See issue #942.
$connection = $installer->getConnection();
if ($connection instanceof Maho\Db\Adapter\Pdo\Mysql) {
    $tables = [
        'catalog/category_anchor_indexer_tmp',
        'catalog/category_anchor_products_indexer_tmp',
        'catalog/category_product_enabled_indexer_tmp',
        'catalog/category_product_indexer_tmp',
        'catalog/product_eav_decimal_indexer_tmp',
        'catalog/product_eav_indexer_tmp',
        'catalog/product_price_indexer_cfg_option_aggregate_tmp',
        'catalog/product_price_indexer_cfg_option_tmp',
        'catalog/product_price_indexer_final_tmp',
        'catalog/product_price_indexer_option_aggregate_tmp',
        'catalog/product_price_indexer_option_tmp',
        'catalog/product_price_indexer_tmp',
    ];

    foreach ($tables as $table) {
        $connection->changeTableEngine($installer->getTable($table), 'InnoDB');
    }
}

$installer->endSetup();
