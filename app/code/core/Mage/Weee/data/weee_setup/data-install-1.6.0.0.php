<?php

/**
 * Maho
 *
 * @package    Mage_Weee
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
