<?php

/**
 * Drops the table of the quote address items, which only the removed multishipping checkout wrote.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->dropTable($installer->getTable('sales_flat_quote_address_item'));

$installer->endSetup();
