<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

/** @var Mage_Weee_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->addAttribute('order_item', 'base_weee_tax_applied_amount', ['type' => 'decimal']);
$installer->addAttribute('order_item', 'base_weee_tax_applied_row_amnt', ['type' => 'decimal']);
$installer->addAttribute('order_item', 'weee_tax_applied_amount', ['type' => 'decimal']);
$installer->addAttribute('order_item', 'weee_tax_applied_row_amount', ['type' => 'decimal']);
$installer->addAttribute('order_item', 'weee_tax_applied', ['type' => 'text']);

$installer->addAttribute('quote_item', 'weee_tax_disposition', ['type' => 'decimal']);
$installer->addAttribute('quote_item', 'weee_tax_row_disposition', ['type' => 'decimal']);
$installer->addAttribute('quote_item', 'base_weee_tax_disposition', ['type' => 'decimal']);
$installer->addAttribute('quote_item', 'base_weee_tax_row_disposition', ['type' => 'decimal']);

$installer->addAttribute('order_item', 'weee_tax_disposition', ['type' => 'decimal']);
$installer->addAttribute('order_item', 'weee_tax_row_disposition', ['type' => 'decimal']);
$installer->addAttribute('order_item', 'base_weee_tax_disposition', ['type' => 'decimal']);
$installer->addAttribute('order_item', 'base_weee_tax_row_disposition', ['type' => 'decimal']);

$installer->addAttribute('invoice_item', 'base_weee_tax_applied_amount', ['type' => 'decimal']);
$installer->addAttribute('invoice_item', 'base_weee_tax_applied_row_amnt', ['type' => 'decimal']);
$installer->addAttribute('invoice_item', 'weee_tax_applied_amount', ['type' => 'decimal']);
$installer->addAttribute('invoice_item', 'weee_tax_applied_row_amount', ['type' => 'decimal']);
$installer->addAttribute('invoice_item', 'weee_tax_applied', ['type' => 'text']);
$installer->addAttribute('invoice_item', 'weee_tax_disposition', ['type' => 'decimal']);
$installer->addAttribute('invoice_item', 'weee_tax_row_disposition', ['type' => 'decimal']);
$installer->addAttribute('invoice_item', 'base_weee_tax_disposition', ['type' => 'decimal']);
$installer->addAttribute('invoice_item', 'base_weee_tax_row_disposition', ['type' => 'decimal']);

$installer->addAttribute('quote_item', 'weee_tax_applied', ['type' => 'text']);
$installer->addAttribute('quote_item', 'weee_tax_applied_amount', ['type' => 'decimal']);
$installer->addAttribute('quote_item', 'weee_tax_applied_row_amount', ['type' => 'decimal']);
$installer->addAttribute('quote_item', 'base_weee_tax_applied_amount', ['type' => 'decimal']);
$installer->addAttribute('quote_item', 'base_weee_tax_applied_row_amnt', ['type' => 'decimal']);

$installer->addAttribute('creditmemo_item', 'weee_tax_disposition', ['type' => 'decimal']);
$installer->addAttribute('creditmemo_item', 'weee_tax_row_disposition', ['type' => 'decimal']);
$installer->addAttribute('creditmemo_item', 'base_weee_tax_disposition', ['type' => 'decimal']);
$installer->addAttribute('creditmemo_item', 'base_weee_tax_row_disposition', ['type' => 'decimal']);
$installer->addAttribute('creditmemo_item', 'weee_tax_applied', ['type' => 'text']);
$installer->addAttribute('creditmemo_item', 'base_weee_tax_applied_amount', ['type' => 'decimal']);
$installer->addAttribute('creditmemo_item', 'base_weee_tax_applied_row_amnt', ['type' => 'decimal']);
$installer->addAttribute('creditmemo_item', 'weee_tax_applied_amount', ['type' => 'decimal']);
$installer->addAttribute('creditmemo_item', 'weee_tax_applied_row_amount', ['type' => 'decimal']);

$installer->endSetup();
