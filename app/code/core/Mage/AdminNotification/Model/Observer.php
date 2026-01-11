<?php

/**
 * Maho
 *
 * @package    Mage_AdminNotification
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_AdminNotification_Model_Observer
{
    /**
     * Predispath admin action controller
     */
    public function preDispatch(\Maho\Event\Observer $observer)
    {
        if (Mage::getSingleton('admin/session')->isLoggedIn()) {
            $feedModel  = Mage::getModel('adminnotification/feed');
            /** @var Mage_AdminNotification_Model_Feed $feedModel */

            $feedModel->checkUpdate();
        }
    }
}
