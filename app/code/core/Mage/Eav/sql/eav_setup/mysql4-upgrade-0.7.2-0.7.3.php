<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Eav_Model_Entity_Setup $installer */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->addColumn($installer->getTable('eav_entity_type'), 'entity_model', 'VARCHAR(255) NOT NULL after entity_type_code');
$installer->getConnection()->addColumn($installer->getTable('eav_entity_type'), 'attribute_model', 'VARCHAR(255) NOT NULL after entity_model');

$installer->endSetup();
