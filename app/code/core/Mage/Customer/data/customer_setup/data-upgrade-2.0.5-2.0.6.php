<?php

/**
 * Drop the table of the old forgot password and magic link limiter. The limits are now under system/rate_limit.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

/** @var Mage_Customer_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->dropTable($installer->getTable('customer_flowpassword'));

$installer->endSetup();
