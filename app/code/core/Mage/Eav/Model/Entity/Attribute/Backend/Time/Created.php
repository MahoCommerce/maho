<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Entity_Attribute_Backend_Time_Created extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Returns date format if it matches a certain mask.
     * @param string $date
     * @return null|string
     */
    protected function _getFormat($date)
    {
        if (is_string($date) && (preg_match('#^\d{4,4}-\d{2,2}-\d{2,2}\s\d{2,2}:\d{2,2}:\d{2,2}$#', $date)
            || preg_match('#^\d{4,4}-\d{2,2}-\d{2,2}\w{1,1}\d{2,2}:\d{2,2}:\d{2,2}[+-]\d{2,2}:\d{2,2}$#', $date))
        ) {
            return 'yyyy-MM-dd HH:mm:ss';
        }
        return null;
    }
    /**
     * Set created date
     * Set created date in UTC time zone
     *
     * @param Mage_Core_Model_Abstract $object
     * @return $this
     */
    #[\Override]
    public function beforeSave($object)
    {
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $date = $object->getData($attributeCode);
        if (is_null($date)) {
            if ($object->isObjectNew()) {
                $object->setData($attributeCode, Mage::app()->getLocale()->formatDateForDb('now'));
            }
        } else {
            // convert to UTC
            $zendDate = Mage::app()->getLocale()->storeToUtc(null, $date);
            $object->setData($attributeCode, $zendDate->format(Mage_Core_Model_Locale::DATETIME_FORMAT));
        }

        return $this;
    }

    /**
     * Convert create date from UTC to current store time zone
     *
     * @param \Maho\DataObject $object
     * @return $this
     */
    #[\Override]
    public function afterLoad($object)
    {
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $date = $object->getData($attributeCode);

        // Handle MySQL zero dates and invalid dates - convert to null
        if (empty($date) || (is_string($date) && preg_match('/^0000-00-00/', $date))) {
            $object->setData($attributeCode);
            parent::afterLoad($object);
            return $this;
        }

        $storeDate = Mage::app()->getLocale()->utcToStore(null, $date);
        $object->setData($attributeCode, $storeDate->format(Mage_Core_Model_Locale::DATETIME_FORMAT));

        parent::afterLoad($object);

        return $this;
    }
}
