<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Product_Attribute_Backend_Startdate_Specialprice extends Mage_Catalog_Model_Product_Attribute_Backend_Startdate
{
    /**
     * Get attribute value for save.
     *
     * @param \Maho\DataObject $object
     * @return string|bool
     */
    #[\Override]
    protected function _getValueForSave($object)
    {
        $attributeName  = $this->getAttribute()->getName();
        $startDate      = $object->getData($attributeName);
        if ($startDate === false) {
            return false;
        }
        if ($startDate == '' && $object->getSpecialPrice()) {
            $startDate = Mage::app()->getLocale()->utcToStore();
        }

        return $startDate;
    }

    /**
     * Before save hook.
     * Prepare attribute value for save
     *
     * @param \Maho\DataObject $object
     * @return Mage_Catalog_Model_Product_Attribute_Backend_Startdate
     */
    #[\Override]
    public function beforeSave($object)
    {
        $startDate = $this->_getValueForSave($object);
        if ($startDate === false) {
            return $this;
        }

        $object->setData($this->getAttribute()->getName(), $startDate);
        parent::beforeSave($object);
        return $this;
    }
}
