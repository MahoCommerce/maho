<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

class Mage_Directory_Model_Country_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Retrieve countries list
     *
     * @return array
     */
    public function items()
    {
        $collection = Mage::getModel('directory/country')->getCollection();

        $result = [];
        foreach ($collection as $country) {
            /** @var Mage_Directory_Model_Country $country */
            $country->getName(); // Loading name in default locale
            $result[] = $country->toArray(['country_id', 'iso2_code', 'iso3_code', 'name']);
        }

        return $result;
    }
}
