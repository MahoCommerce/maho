<?php

/**
 * Maho
 *
 * @package    Mage_Downloadable
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

$installFile = __DIR__ . DS . 'upgrade-1.6.0.0.1-1.6.0.0.2.php';
if (file_exists($installFile)) {
    include $installFile;
}

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

/** @var Maho\Db\Adapter\Pdo\Mysql $connection */
$connection = $installer->getConnection();

$connection->changeTableEngine(
    $installer->getTable('downloadable/product_price_indexer_tmp'),
    Maho\Db\Adapter\Pdo\Mysql::ENGINE_MEMORY,
);
