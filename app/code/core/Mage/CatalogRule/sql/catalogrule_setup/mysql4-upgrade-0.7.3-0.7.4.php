<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_CatalogRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2022 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->changeColumn(
    $this->getTable('catalogrule'),
    'conditions_serialized',
    'conditions_serialized',
    'mediumtext CHARACTER SET utf8 NOT NULL'
);
$installer->getConnection()->changeColumn(
    $this->getTable('catalogrule'),
    'actions_serialized',
    'actions_serialized',
    'mediumtext CHARACTER SET utf8 NOT NULL'
);

$installer->endSetup();
