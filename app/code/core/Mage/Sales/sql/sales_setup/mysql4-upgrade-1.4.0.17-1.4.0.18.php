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

/** @var Mage_Sales_Model_Entity_Setup $installer */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->addColumn(
    $installer->getTable('sales/invoice_comment'),
    'is_visible_on_front',
    'tinyint(1) unsigned not null default 0 after `is_customer_notified`'
);
$installer->getConnection()->addColumn(
    $installer->getTable('sales/shipment_comment'),
    'is_visible_on_front',
    'tinyint(1) unsigned not null default 0 after `is_customer_notified`'
);
$installer->getConnection()->addColumn(
    $installer->getTable('sales/creditmemo_comment'),
    'is_visible_on_front',
    'tinyint(1) unsigned not null default 0 after `is_customer_notified`'
);

$installer->endSetup();
