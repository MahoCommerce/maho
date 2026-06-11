<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;
$connection = $installer->getConnection();

// MySQL-specific optimization: use MEMORY engine for temporary indexer tables
if ($connection instanceof Maho\Db\Adapter\Pdo\Mysql) {
    $memoryTables = [
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

    foreach ($memoryTables as $table) {
        $connection->changeTableEngine($installer->getTable($table), Maho\Db\Adapter\Pdo\Mysql::ENGINE_MEMORY);
    }
}
