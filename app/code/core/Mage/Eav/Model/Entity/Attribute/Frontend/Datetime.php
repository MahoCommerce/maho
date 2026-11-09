<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Entity_Attribute_Frontend_Datetime extends Mage_Eav_Model_Entity_Attribute_Frontend_Abstract
{
    /**
     * Retrieve attribute value
     *
     * @return mixed
     */
    #[\Override]
    public function getValue(\Maho\DataObject $object)
    {
        $value = parent::getValue($object);
        if (!$value) {
            return '';
        }

        // A date value has no time zone, so a conversion to the store time zone can move it to another day
        return Mage::helper('core')->formatTimezoneDate($value, Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM, useTimezone: false);
    }
}
