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
    ALTER TABLE {$this->getTable('admin/user')}
    ADD COLUMN `password_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN `passkey_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN `passkey_credential_id_hash` varchar(255) NULL,
    ADD COLUMN `passkey_public_key` VARCHAR(255) NULL
");

$installer->endSetup();
