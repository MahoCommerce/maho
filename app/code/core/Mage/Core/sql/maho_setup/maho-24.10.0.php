<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->getConnection()
    ->addColumn($installer->getTable('core/resource'), 'maho_version', 'varchar(50)');

$installer->endSetup();
