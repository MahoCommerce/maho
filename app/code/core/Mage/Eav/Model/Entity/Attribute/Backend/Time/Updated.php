<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Entity_Attribute_Backend_Time_Updated extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Set modified date
     *
     * @param \Maho\DataObject $object
     * @return $this
     */
    #[\Override]
    public function beforeSave($object)
    {
        $object->setData($this->getAttribute()->getAttributeCode(), Mage::app()->getLocale()->formatDateForDb('now'));
        return $this;
    }

    /**
     * Convert update date after load
     *
     * @param \Maho\DataObject $object
     * @return $this
     */
    #[\Override]
    public function afterLoad($object)
    {
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $date = $object->getData($attributeCode);

        // Handle MySQL zero dates and invalid dates
        if (empty($date) || (is_string($date) && preg_match('/^0000-00-00/', $date))) {
            $object->setData($attributeCode);
            parent::afterLoad($object);
            return $this;
        }

        parent::afterLoad($object);
        return $this;
    }
}
