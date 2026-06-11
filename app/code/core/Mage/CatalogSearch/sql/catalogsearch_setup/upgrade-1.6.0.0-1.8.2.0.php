<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogSearch
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$connection = $installer->getConnection();

$tableName = $installer->getTable('catalogsearch/search_query');
$indexNameToCreate = $installer->getIdxName($tableName, ['synonym_for']);
$connection->addIndex($tableName, $indexNameToCreate, ['synonym_for']);
