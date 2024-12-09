<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Bundle
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Catalog_Model_Resource_Setup  $installer */
$installer = $this;
$installer->startSetup();
$installer->getConnection()->addKey(
    $installer->getTable('bundle/option_value'),
    'UNQ_OPTION_STORE',
    ['option_id', 'store_id'],
    'unique'
);
$installer->endSetup();
