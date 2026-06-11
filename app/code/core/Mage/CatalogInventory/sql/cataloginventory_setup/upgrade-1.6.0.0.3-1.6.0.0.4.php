<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogInventory
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Revert the 2012-era MEMORY-engine optimization on the stock indexer `_tmp`
// table. On MySQL 8.4+ `enforce_gtid_consistency=ON` is the default, which
// forbids mixing writes to non-transactional (MEMORY) and transactional
// (InnoDB) tables in the same transaction. The stock indexer runs inside the
// order transaction during checkout, so the MEMORY table breaks checkout out
// of the box on stock MySQL 8.4+. See issue #942.
$connection = $installer->getConnection();
if ($connection instanceof Maho\Db\Adapter\Pdo\Mysql) {
    $connection->changeTableEngine(
        $installer->getTable('cataloginventory/stock_status_indexer_tmp'),
        'InnoDB',
    );
}

$installer->endSetup();
