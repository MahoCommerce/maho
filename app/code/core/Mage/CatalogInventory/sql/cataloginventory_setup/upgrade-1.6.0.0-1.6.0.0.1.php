<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogInventory
 */

/** @var Mage_Eav_Model_Entity_Setup $this */
$installer = $this;
$connection = $installer->getConnection();

// MySQL-specific optimization: use MEMORY engine for temporary indexer table
if ($connection instanceof Maho\Db\Adapter\Pdo\Mysql) {
    $connection->changeTableEngine(
        $installer->getTable('cataloginventory/stock_status_indexer_tmp'),
        Maho\Db\Adapter\Pdo\Mysql::ENGINE_MEMORY,
    );
}
