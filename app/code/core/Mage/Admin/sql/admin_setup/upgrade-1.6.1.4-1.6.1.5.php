<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->addColumn($installer->getTable('admin/user'), 'backend_locale', 'varchar(8) NULL');

$installer->endSetup();
