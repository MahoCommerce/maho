<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

class Mage_Customer_Model_Resource_Address_Attribute_Backend_Region extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Prepare object for save
     *
     * @param \Maho\DataObject|Mage_Customer_Model_Address $object
     * @return $this
     */
    #[\Override]
    public function beforeSave($object)
    {
        $region = $object->getData('region');
        if (is_numeric($region)) {
            $regionModel = Mage::getModel('directory/region')->load($region);
            if ($regionModel->getId() && $object->getCountryId() == $regionModel->getCountryId()) {
                $object->setRegionId($regionModel->getId())
                    ->setRegion($regionModel->getName());
            }
        }
        return $this;
    }
}
