<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Block_Product_Send extends Mage_Catalog_Block_Product_Abstract
{
    /**
     * Retrieve username for form field
     *
     * @return string
     */

    public function getUserName()
    {
        return Mage::getSingleton('customer/session')->getCustomer()->getName();
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return (string) Mage::getSingleton('customer/session')->getCustomer()->getEmail();
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getProductId()
    {
        return $this->getRequest()->getParam('id');
    }

    /**
     * @return int
     */
    public function getMaxRecipients()
    {
        $sendToFriendModel = Mage::registry('send_to_friend_model');
        return $sendToFriendModel->getMaxRecipients();
    }
}
