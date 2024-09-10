<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2022 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$connection = $installer->getConnection();

$tableName = $installer->getTable('catalogsearch/search_query');
$indexNameToCreate = $installer->getIdxName($tableName, ['synonym_for']);
$connection->addIndex($tableName, $indexNameToCreate, ['synonym_for']);
