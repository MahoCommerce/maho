<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_CatalogInventory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Eav_Model_Entity_Setup $this */
$installer = $this;

/** @var Varien_Db_Adapter_Pdo_Mysql $connection */
$connection = $installer->getConnection();

$connection->changeTableEngine(
    $installer->getTable('cataloginventory/stock_status_indexer_tmp'),
    Varien_Db_Adapter_Pdo_Mysql::ENGINE_MEMORY
);
