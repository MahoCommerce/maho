<?php

/**
 * Maho
 *
 * @package    Mage_CatalogInventory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
