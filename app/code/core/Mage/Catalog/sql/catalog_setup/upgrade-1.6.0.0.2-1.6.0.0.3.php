<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

$tableName = $installer->getTable('catalog/product_index_eav_decimal');
$indexName = $installer->getConnection()->getPrimaryKeyName($tableName);

$tableNameTmp = $installer->getTable('catalog/product_eav_decimal_indexer_tmp');
$indexNameTmp = $installer->getConnection()->getPrimaryKeyName($tableNameTmp);

$fields = ['entity_id', 'attribute_id', 'store_id'];

$installer->getConnection()
        ->addIndex($tableName, $indexName, $fields, Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_PRIMARY);

$installer->getConnection()
        ->addIndex($tableNameTmp, $indexNameTmp, $fields, Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_PRIMARY);
