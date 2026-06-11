<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Resource_Quote_Address_Attribute_Backend_Parent extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Save items collection and shipping rates collection
     *
     * @param \Maho\DataObject|Mage_Sales_Model_Quote_Address $object
     * @return $this
     */
    #[\Override]
    public function afterSave($object)
    {
        parent::afterSave($object);

        $object->getItemsCollection()->save();
        $object->getShippingRatesCollection()->save();

        return $this;
    }
}
