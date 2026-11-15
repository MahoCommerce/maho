<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Allregion
{
    protected ?array $_countries = null;
    protected ?array $_options = null;

    public function toOptionArray(bool $isMultiselect = false): array
    {
        if (!$this->_options) {
            $countriesArray = Mage::getResourceModel('directory/country_collection')->load()
                ->toOptionArray(false);
            $this->_countries = [];
            foreach ($countriesArray as $a) {
                $this->_countries[$a['value']] = $a['label'];
            }

            $countryRegions = [];
            $regionsCollection = Mage::getResourceModel('directory/region_collection')->load();
            foreach ($regionsCollection as $region) {
                $countryRegions[$region->getCountryId()][$region->getId()] = $region->getDefaultName();
            }
            uksort($countryRegions, $this->sortRegionCountries(...));

            $this->_options = [];
            foreach ($countryRegions as $countryId => $regions) {
                $regionOptions = [];
                foreach ($regions as $regionId => $regionName) {
                    $regionOptions[] = ['label' => $regionName, 'value' => $regionId];
                }
                $this->_options[] = ['label' => $this->_countries[$countryId], 'value' => $regionOptions];
            }
        }
        $options = $this->_options;
        if (!$isMultiselect) {
            array_unshift($options, ['value' => '', 'label' => '']);
        }

        return $options;
    }

    public function sortRegionCountries(string $a, string $b): int
    {
        return strcmp($this->_countries[$a], $this->_countries[$b]);
    }
}
