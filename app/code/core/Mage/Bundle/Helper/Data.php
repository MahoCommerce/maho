<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
 */

class Mage_Bundle_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const string XML_NODE_BUNDLE_PRODUCT_TYPE = 'global/catalog/product/type/bundle';

    #[\Override]
    protected $_moduleName = 'Mage_Bundle';

    /**
     * Retrieve array of allowed product types for bundle selection product
     *
     * @return array
     */
    public function getAllowedSelectionTypes()
    {
        $config = Mage::getConfig()->getNode(self::XML_NODE_BUNDLE_PRODUCT_TYPE);
        return array_keys($config->allowed_selection_types->asArray());
    }

    /**
     * Return the price model of the bundle product $product
     */
    public function getPriceModel(Mage_Catalog_Model_Product $product): Mage_Bundle_Model_Product_Price
    {
        /** @var Mage_Bundle_Model_Product_Price $priceModel */
        $priceModel = $product->getPriceModel();
        return $priceModel;
    }
}
