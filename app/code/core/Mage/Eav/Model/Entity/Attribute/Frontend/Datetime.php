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
        $data = '';
        $value = parent::getValue($object);
        $format = Mage::app()->getLocale()->getDateFormat(
            Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM,
        );

        if ($value) {
            $data = (new DateTime($value))->format($format);
        }

        return $data;
    }
}
