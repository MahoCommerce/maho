<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2022 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Sales_Model_Resource_Setup $installer */
$installer = $this;

$installer->run("
ALTER TABLE `{$installer->getTable('sales_flat_quote_address_item')}`
    ADD COLUMN `parent_item_id` INTEGER UNSIGNED AFTER `address_item_id`,
    ADD KEY `IDX_PARENT_ITEM_ID` (`parent_item_id`);
");

$installer->getConnection()->addConstraint(
    'SALES_FLAT_QUOTE_ADDRESS_ITEM_PARENT',
    $installer->getTable('sales_flat_quote_address_item'),
    'parent_item_id',
    $installer->getTable('sales_flat_quote_address_item'),
    'address_item_id'
);
