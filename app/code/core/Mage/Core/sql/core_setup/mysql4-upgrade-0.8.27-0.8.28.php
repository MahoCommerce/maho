<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2022 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;

$tagsTableName = $installer->getTable('core/cache_tag');
$installer->getConnection()->truncate($tagsTableName);
$installer->getConnection()->modifyColumn($tagsTableName, 'tag', 'VARCHAR(100)');
$installer->getConnection()->modifyColumn($tagsTableName, 'cache_id', 'VARCHAR(200)');
$installer->getConnection()->addKey($tagsTableName, '', ['tag', 'cache_id'], 'PRIMARY');
$installer->getConnection()->dropKey($tagsTableName, 'IDX_TAG');
