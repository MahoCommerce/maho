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

/**
 * Add new columns to the customer_group table allowing each group to be assigned
 * an attribute set for both the customer and customer_address entity types.
 *
 * If there are no attribute sets available, create a new one and set as default.
 */
$defs = [
    [ 'model' => 'customer/customer', 'column' => 'customer_attribute_set_id' ],
    [ 'model' => 'customer/address', 'column' => 'customer_address_attribute_set_id' ],
];

foreach ($defs as $info) {
    /** @var Mage_Eav_Model_Entity_Type $entityType */
    $entityType = Mage::getResourceModel($info['model'])->getEntityType();

    /** $var Mage_Eav_Model_Resource_Entity_Attribute_Set_Collection $collection */
    $collection = Mage::getResourceModel('eav/entity_attribute_set_collection')
                ->setEntityTypeFilter($entityType->getId());

    /** @var Mage_Eav_Model_Entity_Attribute_Set $defaultSet */
    $defaultSet = $collection->getItemById($entityType->getDefaultAttributeSetId())
                ?? $collection->getFirstItem();

    if ($defaultSet->getId() === null) {
        /** @var Mage_Eav_Model_Entity_Attribute_Set $defaultSet */
        $defaultSet = Mage::getModel('eav/entity_attribute_set')
                    ->setEntityTypeId($entityType->getId())
                    ->setAttributeSetName('Default')
                    ->save();

        /** @var Mage_Eav_Model_Entity_Attribute_Group $modelGroup */
        $modelGroup = Mage::getModel('eav/entity_attribute_group')
                    ->setAttributeGroupName('General')
                    ->setAttributeSetId($defaultSet->getId())
                    ->setSortOrder(1)
                    ->setDefaultId(1)
                    ->save();

        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $attributes */
        $attributes = Mage::getResourceModel($entityType->getEntityAttributeCollection())
                    ->setEntityTypeFilter($entityType->getId())
                    ->load();

        /** @var Mage_Eav_Model_Entity_Attribute $attribute */
        foreach ($attributes->getItems() as $attribute) {
            $attribute->setAttributeGroupId($modelGroup->getId())
                      ->setAttributeSetId($defaultSet->getId())
                      ->save();
        }
    }

    if ($entityType->getDefaultAttributeSetId() !== $defaultSet->getId()) {
        $entityType->setDefaultAttributeSetId($defaultSet->getId())->save();
    }

    $installer->getConnection()
              ->addColumn($installer->getTable('customer/customer_group'), $info['column'], [
                  'type'     => Varien_Db_Ddl_Table::TYPE_SMALLINT,
                  'unsigned' => true,
                  'nullable' => false,
                  'default'  => $defaultSet->getId(),
                  'comment'  => 'Customer Group Attribute Set ID',
              ]);
}

$installer->endSetup();
