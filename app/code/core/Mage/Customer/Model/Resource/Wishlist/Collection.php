<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customers wishlist collection
 *
 * @category   Mage
 * @package    Mage_Customer
 */
class Mage_Customer_Model_Resource_Wishlist_Collection extends Mage_Eav_Model_Entity_Collection_Abstract
{
    /**
     * Set entity
     */
    #[\Override]
    protected function _construct()
    {
        $this->setEntity(Mage::getResourceSingleton('customer/wishlist'));
    }
}
