<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->getConnection()
          ->dropTable($installer->getTable('persistent_session'));

$installer->getConnection()
          ->dropColumn($installer->getTable('sales/quote'), 'is_persistent');

$installer->endSetup();
