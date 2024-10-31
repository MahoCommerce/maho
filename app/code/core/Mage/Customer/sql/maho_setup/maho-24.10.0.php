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

$defs = [
    [ 'model' => 'customer/customer', 'column' => 'customer_attribute_set_id' ],
    [ 'model' => 'customer/address', 'column' => 'customer_address_attribute_set_id' ],
];

foreach ($defs as $info) {
    $entityTypeId = Mage::getResourceModel($info['model'])
                  ->getEntityType()
                  ->getId();

    $defaultSet = Mage::getResourceModel('eav/entity_attribute_set_collection')
                ->setEntityTypeFilter($entityTypeId)
                ->getFirstItem();

    if ($defaultSet->getAttributeSetId() === null) {
        $defaultSet = Mage::getModel('eav/entity_attribute_set')
                    ->setEntityTypeId($entityTypeId)
                    ->setAttributeSetName('Default')
                    ->save();
    }

    $installer->getConnection()
              ->addColumn($installer->getTable('customer/customer_group'), $info['column'], [
                  'type'     => Varien_Db_Ddl_Table::TYPE_SMALLINT,
                  'unsigned' => true,
                  'nullable' => false,
                  'default'  => $defaultSet->getAttributeSetId(),
                  'comment'  => 'Customer Group Attribute Set ID',
              ]);
}

$installer->endSetup();
