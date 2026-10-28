<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

class Mage_Customer_Model_Form extends Mage_Eav_Model_Form
{
    /**
     * Current module pathname
     *
     * @var string
     */
    #[\Override]
    protected $_moduleName = 'customer';

    /**
     * Current EAV entity type code
     *
     * @var string
     */
    #[\Override]
    protected $_entityTypeCode = 'customer';

    /**
     * Get EAV Entity Form Attribute Collection for Customer
     * exclude 'created_at'
     *
     * @return Mage_Customer_Model_Resource_Form_Attribute_Collection
     */
    protected ?int $customerGroupId = null;

    #[\Override]
    protected function _getFormAttributeCollection()
    {
        $collection = parent::_getFormAttributeCollection()
            ->addFieldToFilter('attribute_code', ['neq' => 'created_at']);
        $setId = $this->getAttributeSetId();
        if ($setId) {
            $collection->addAttributeSetFilter($setId);
        }
        return $collection;
    }

    /**
     * Set the customer group whose attribute set the form uses, when the entity does not tell it.
     */
    public function setCustomerGroupId(?int $groupId): static
    {
        $this->customerGroupId = $groupId;
        $this->_attributes = null;
        return $this;
    }

    /**
     * Return the attribute set of the customer group of the form, or null when there is no entity.
     */
    public function getAttributeSetId(): ?int
    {
        if ($this->_entity === null) {
            return null;
        }
        return Mage::helper('customer')->getGroupAttributeSetId(
            $this->getCustomerGroupId(),
            (string) $this->getEntityType()->getEntityTypeCode(),
        );
    }

    /**
     * Return the customer group of the form: the one set on the form, then the one of the entity,
     * then the one of the customer session.
     */
    public function getCustomerGroupId(): int
    {
        if ($this->customerGroupId !== null) {
            return $this->customerGroupId;
        }

        $entity = $this->_entity;
        if ($entity instanceof Mage_Customer_Model_Customer) {
            return (int) $entity->getGroupId();
        }
        if ($entity instanceof Mage_Sales_Model_Quote_Address) {
            return (int) $entity->getQuote()?->getCustomerGroupId();
        }
        if ($entity instanceof Mage_Sales_Model_Order_Address) {
            return (int) $entity->getOrder()?->getCustomerGroupId();
        }
        if ($entity instanceof Mage_Customer_Model_Address && $entity->getCustomer()) {
            return (int) $entity->getCustomer()->getGroupId();
        }

        // A new address without a customer belongs to a customer who registers now
        $session = Mage::getSingleton('customer/session');
        return $session->isLoggedIn()
            ? (int) $session->getCustomerGroupId()
            : Mage::helper('customer')->getDefaultCustomerGroupId($this->getStore());
    }
}
