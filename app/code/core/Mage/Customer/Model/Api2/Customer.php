<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * API2 class for customer
 *
 * @package    Mage_Customer
 */
class Mage_Customer_Model_Api2_Customer extends Mage_Api2_Model_Resource
{
    /**
     * Resource specific method to retrieve attributes' codes. May be overridden in child.
     *
     * @return array
     */
    #[\Override]
    protected function _getResourceAttributes()
    {
        return $this->getEavAttributes(Mage_Api2_Model_Auth_User_Admin::USER_TYPE != $this->getUserType(), true);
    }
}
