<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2019-2022 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Mage_Eav_Model_Observer
 *
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Model_Observer
{
    /**
     * @param Varien_Event_Observer $event
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
}
