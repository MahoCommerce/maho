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

$tableName = $installer->getTable('catalog/product_index_eav_decimal');
$indexName = $installer->getConnection()->getPrimaryKeyName($tableName);

$tableNameTmp = $installer->getTable('catalog/product_eav_decimal_indexer_tmp');
$indexNameTmp = $installer->getConnection()->getPrimaryKeyName($tableNameTmp);

$fields = ['entity_id', 'attribute_id', 'store_id'];

$installer->getConnection()
        ->addIndex($tableName, $indexName, $fields, Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_PRIMARY);

$installer->getConnection()
        ->addIndex($tableNameTmp, $indexNameTmp, $fields, Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_PRIMARY);
