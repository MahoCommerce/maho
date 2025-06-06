<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Customer_Model_Api2_Customer_Rest_Customer_V1 extends Mage_Customer_Model_Api2_Customer_Rest
{
    /**
     * Is customer has rights to retrieve/update customer item
     *
     * @param int $customerId
     * @throws Mage_Api2_Exception
     * @return bool
     */
    protected function _isOwner($customerId)
    {
        if ($this->getApiUser()->getUserId() !== $customerId) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        return true;
    }

    /**
     * Retrieve information about customer
     *
     * @throws Mage_Api2_Exception
     * @return array|void
     */
    #[\Override]
    protected function _retrieve()
    {
        if ($this->_isOwner($this->getRequest()->getParam('id'))) {
            return parent::_retrieve();
        }
    }

    #[\Override]
    protected function _getCollectionForRetrieve()
    {
        return parent::_getCollectionForRetrieve()->addAttributeToFilter('entity_id', $this->getApiUser()->getUserId());
    }

    /**
     * Update customer
     *
     * @throws Mage_Api2_Exception
     */
    #[\Override]
    protected function _update(array $data)
    {
        if ($this->_isOwner($this->getRequest()->getParam('id'))) {
            parent::_update($data);
        }
    }

    /**
     * Update customers
     *
     * @throws Mage_Api2_Exception
     */
    protected function _multiUpdate(array $data)
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED, Mage_Api2_Model_Server::HTTP_FORBIDDEN);
    }
}
