<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2019-2022 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Eav_Model_Observer
{
    /**
     * @param \Maho\Event\Observer $event
     * @return void
     * @throws Mage_Core_Model_Store_Exception
     */
    public function onControllerActionPredispatch($event)
    {
        /** @var Mage_Core_Controller_Varien_Action $controllerAction */
        $controllerAction = $event->getData('controller_action');

        // initialize cached store_id for frontend controllers only to avoid issues with cron jobs and admin controllers which sometimes change store view
        if ($controllerAction instanceof Mage_Core_Controller_Front_Action) {
            Mage::getSingleton('eav/config')->setCurrentStoreId(Mage::app()->getStore()->getId());
        }
    }

    public function cleanOrphanedRecords()
    {
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');
        $entityAttributeTable = $resource->getTableName('eav_entity_attribute');
        $attributeSetTable = $resource->getTableName('eav_attribute_set');
        $entityTypes = Mage::getModel('eav/entity_type')->getCollection();
        foreach ($entityTypes as $entityType) {
            $entityTypeId = $entityType->getEntityTypeId();

            try {
                $entityTable = $resource->getTableName($entityType->getEntityTable());
            } catch (Mage_Core_Exception $e) {
                // If the entityTable doesn't exist, it could be an entity created by a module
                // that was later removed, simply skip it at the moment
                continue;
            }

            $attributeSets = $connection->fetchCol("SELECT attribute_set_id FROM $attributeSetTable WHERE entity_type_id=$entityTypeId");
            $attributeTables = [
                "{$entityTable}_char",
                "{$entityTable}_datetime",
                "{$entityTable}_decimal",
                "{$entityTable}_int",
                "{$entityTable}_text",
                "{$entityTable}_varchar",
            ];
            foreach ($attributeTables as $table) {
                if (!$connection->isTableExists($table)) {
                    continue;
                }

                foreach ($attributeSets as $attributeSetId) {
                    try {
                        $connection->query("
                            DELETE FROM $table WHERE entity_type_id=$entityTypeId
                            AND entity_id IN (
                                SELECT entity_id from $entityTable WHERE entity_type_id=$entityTypeId AND attribute_set_id=$attributeSetId
                            )
                            AND attribute_id NOT IN (
                                SELECT attribute_id FROM eav_entity_attribute WHERE entity_type_id=$entityTypeId AND attribute_set_id=$attributeSetId
                            )
                        ");
                    } catch (Exception $e) {
                        Mage::logException($e);
                    }
                }
            }
        }
    }
}
