<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

abstract class Mage_Catalog_Block_Product_View_Abstract extends Mage_Catalog_Block_Product_Abstract
{
    /**
     * Retrieve product
     *
     * @return Mage_Catalog_Model_Product
     */
    #[\Override]
    public function getProduct()
    {
        $product = parent::getProduct();
        if (is_null($product->getTypeInstance(true)->getStoreFilter($product))) {
            $product->getTypeInstance(true)->setStoreFilter(Mage::app()->getStore(), $product);
        }

        return $product;
    }
}
