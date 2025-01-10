<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Checkout_Model_Resource_Setup $this */
$installer = $this;

$installer->startSetup();

$installer->run("
    ALTER TABLE {$this->getTable('sales/order_status')}
    ADD COLUMN `color` VARCHAR(20) NULL,
");

$installer->endSetup();
