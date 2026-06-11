<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Product_Attribute_Source_Countryofmanufacture extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    /**
     * Get list of all available countries
     * @return mixed
     */
    #[\Override]
    public function getAllOptions()
    {
        $cacheKey = 'DIRECTORY_COUNTRY_SELECT_STORE_' . Mage::app()->getStore()->getCode();
        if (Mage::app()->useCache('config') && $cache = Mage::app()->loadCache($cacheKey)) {
            $options = $cache;
        } else {
            $collection = Mage::getModel('directory/country')->getResourceCollection();
            if (!Mage::app()->getStore()->isAdmin()) {
                $collection->loadByStore();
            }
            $options = $collection->toOptionArray();
            if (Mage::app()->useCache('config')) {
                Mage::app()->saveCache($options, $cacheKey, ['config']);
            }
        }
        return $options;
    }
}
