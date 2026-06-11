<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Resource_Order_Attribute_Backend_Billing extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Perform operation before save
     *
     * @param \Maho\DataObject $object
     * @return $this
     */
    #[\Override]
    public function beforeSave($object)
    {
        $billingAddressId = $object->getBillingAddressId();
        if (is_null($billingAddressId)) {
            $object->unsetBillingAddressId();
        }
        return $this;
    }

    /**
     * Perform operation after save
     *
     * @param \Maho\DataObject $object
     * @return $this
     */
    #[\Override]
    public function afterSave($object)
    {
        $billingAddressId = false;
        foreach ($object->getAddressesCollection() as $address) {
            if ($address->getAddressType() == 'billing') {
                $billingAddressId = $address->getId();
            }
        }
        if ($billingAddressId) {
            $object->setBillingAddressId($billingAddressId);
            $this->getAttribute()->getEntity()->saveAttribute($object, $this->getAttribute()->getAttributeCode());
        }
        return $this;
    }
}
