<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

/**
 * Customer Address Postal/Zip Code Attribute Data Model
 * This Data Model Has to Be Set Up in additional EAV attribute table
 *
 * @package    Mage_Customer
 */
class Mage_Customer_Model_Attribute_Data_Postcode extends Mage_Eav_Model_Attribute_Data_Text
{
    /**
     * Validate postal/zip code
     * Skip the required check if the zip code of the country is optional
     *
     * @param array|string $value
     * @return bool|array
     */
    #[\Override]
    public function validateValue($value)
    {
        $countryId      = $this->getExtractedData('country_id');
        $optionalZip    = Mage::helper('directory')->getCountriesWithOptionalZip();
        if (!in_array($countryId, $optionalZip)) {
            $result = parent::validateValue($value);
            if ($result !== true) {
                return $result;
            }
        }

        if (is_string($countryId) && is_string($value)) {
            $error = Mage::helper('customer/address')->getPostcodeFormatError($countryId, $value);
            if ($error !== null) {
                return [$error];
            }
        }
        return true;
    }
}
