<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Resource_Quote_Address_Attribute_Backend_Region extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Set region to the attribute
     *
     * @param \Maho\DataObject|Mage_Sales_Model_Quote_Address $object
     * @return $this
     */
    #[\Override]
    public function beforeSave($object)
    {
        if (is_numeric($object->getRegion())) {
            $region = Mage::getModel('directory/region')->load((int) $object->getRegion());
            if ($region) {
                $object->setRegionId($region->getId());
                $object->setRegion($region->getCode());
            }
        }

        return $this;
    }
}
