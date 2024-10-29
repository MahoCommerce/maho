<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Customer_Model_Entity_Setup $installer */
$installer = $this;
$installer->startSetup();

$installer->getConnection()
    ->addColumn($installer->getTable('customer/customer_group'), 'customer_attribute_set_id', [
        'type'     => Varien_Db_Ddl_Table::TYPE_SMALLINT,
        'unsigned' => true,
        'nullable' => false,
        'default'  => Mage_Customer_Model_Group::DEFAULT_ATTRIBUTE_SET_ID,
        'comment'  => 'Customer Group Attribute Set ID',
    ]);

$installer->getConnection()
    ->addColumn($installer->getTable('customer/customer_group'), 'customer_address_attribute_set_id', [
        'type'     => Varien_Db_Ddl_Table::TYPE_SMALLINT,
        'unsigned' => true,
        'nullable' => false,
        'default'  => Mage_Customer_Model_Group::DEFAULT_ADDRESS_ATTRIBUTE_SET_ID,
        'comment'  => 'Customer Group Address Attribute Set ID',
    ]);

$installer->endSetup();
