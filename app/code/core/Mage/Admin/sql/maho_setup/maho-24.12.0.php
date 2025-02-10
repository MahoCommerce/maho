<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Checkout_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->run("
    ALTER TABLE {$this->getTable('admin/user')}
    ADD COLUMN `twofa_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN `twofa_secret` VARCHAR(255) NULL
");

$installer->endSetup();
