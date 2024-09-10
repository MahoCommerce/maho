<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Paypal_Model_Resource_Setup $installer */
$installer = $this;

$installer->getConnection()
    ->addColumn($installer->getTable('paypal/settlement_report_row'), 'store_id', [
        'type'    => Varien_Db_Ddl_Table::TYPE_TEXT,
        'comment' => 'Store ID',
        'length'  => '50']);
