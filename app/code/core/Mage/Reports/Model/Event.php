<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

/**
 * @method Mage_Reports_Model_Resource_Event _getResource()
 * @method Mage_Reports_Model_Resource_Event getResource()
 * @method Mage_Reports_Model_Resource_Event_Collection getCollection()
 */
class Mage_Reports_Model_Event extends Mage_Core_Model_Abstract
{
    public const EVENT_PRODUCT_VIEW    = 1;
    public const EVENT_PRODUCT_SEND    = 2;
    public const EVENT_PRODUCT_COMPARE = 3;
    public const EVENT_PRODUCT_TO_CART = 4;
    public const EVENT_PRODUCT_TO_WISHLIST = 5;
    public const EVENT_WISHLIST_SHARE  = 6;

    /**
     * Initialize resource
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('reports/event');
    }

    #[\Override]
    protected function _beforeSave()
    {
        $this->setLoggedAt(Mage::app()->getLocale()->formatDateForDb('now'));
        return parent::_beforeSave();
    }

    /**
     * Update customer type after customer login
     *
     * @param int $visitorId
     * @param int $customerId
     * @param array $types
     * @return $this
     */
    public function updateCustomerType($visitorId, $customerId, $types = null)
    {
        if (is_null($types)) {
            $types = [];
            foreach (Mage::getModel('reports/event_type')->getCollection() as $eventType) {
                if ($eventType->getCustomerLogin()) {
                    $types[$eventType->getId()] = $eventType->getId();
                }
            }
        }
        $this->getResource()->updateCustomerType($this, $visitorId, $customerId, $types);
        return $this;
    }

    /**
     * Clean events (visitors)
     *
     * @return $this
     */
    public function clean()
    {
        $this->getResource()->clean($this);
        return $this;
    }

    public function getEventTypeId(): ?int
    {
        $value = $this->getData('event_type_id');
        return $value === null ? null : (int) $value;
    }

    public function setEventTypeId(?int $value): static
    {
        return $this->setData('event_type_id', $value);
    }

    public function getLoggedAt(): ?string
    {
        $value = $this->getData('logged_at');
        return $value === null ? null : (string) $value;
    }

    public function setLoggedAt(?string $value): static
    {
        return $this->setData('logged_at', $value);
    }

    public function getObjectId(): ?int
    {
        $value = $this->getData('object_id');
        return $value === null ? null : (int) $value;
    }

    public function setObjectId(?int $value): static
    {
        return $this->setData('object_id', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getSubjectId(): ?int
    {
        $value = $this->getData('subject_id');
        return $value === null ? null : (int) $value;
    }

    public function setSubjectId(?int $value): static
    {
        return $this->setData('subject_id', $value);
    }

    public function getSubtype(): ?int
    {
        $value = $this->getData('subtype');
        return $value === null ? null : (int) $value;
    }

    public function setSubtype(?int $value): static
    {
        return $this->setData('subtype', $value);
    }

}
