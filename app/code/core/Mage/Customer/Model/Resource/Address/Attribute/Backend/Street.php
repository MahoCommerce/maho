<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

class Mage_Customer_Model_Resource_Address_Attribute_Backend_Street extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Prepare object for save
     *
     * @param Mage_Customer_Model_Address_Abstract $object
     * @return $this
     */
    #[\Override]
    public function beforeSave($object)
    {
        $street = $object->getStreet(-1);
        if ($street) {
            $object->implodeStreetAddress();
        }
        return $this;
    }
}
