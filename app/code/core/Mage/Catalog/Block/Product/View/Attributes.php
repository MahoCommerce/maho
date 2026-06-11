<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Block_Product_View_Attributes extends Mage_Core_Block_Template
{
    protected $_product = null;

    /**
     * @return mixed|null
     */
    public function getProduct()
    {
        if (!$this->_product) {
            $this->_product = Mage::registry('product');
        }
        return $this->_product;
    }

    /**
     * $excludeAttr is optional array of attribute codes to
     * exclude them from additional data array
     *
     * @return array
     */
    public function getAdditionalData(array $excludeAttr = [])
    {
        $data = [];
        $product = $this->getProduct();
        $attributes = $product->getAttributes();
        foreach ($attributes as $attribute) {
            if ($attribute->getIsVisibleOnFront() && !in_array($attribute->getAttributeCode(), $excludeAttr)) {
                $value = $attribute->getFrontend()->getValue($product);

                if (!$product->hasData($attribute->getAttributeCode())) {
                    // Skip file attributes when they have no data
                    if ($attribute->getFrontendInput() == 'file') {
                        continue;
                    }
                    $value = Mage::helper('catalog')->__('N/A');
                } elseif (is_null($value) || $value === false || $value === '') {
                    // Skip file attributes when empty instead of showing "No"
                    if ($attribute->getFrontendInput() == 'file') {
                        continue;
                    }
                    $value = Mage::helper('catalog')->__('No');
                } elseif ($attribute->getFrontendInput() == 'price' && is_string($value)) {
                    $value = Mage::app()->getStore()->convertPrice($value, true);
                }

                if (is_string($value) && strlen($value)) {
                    $data[$attribute->getAttributeCode()] = [
                        'label' => $attribute->getStoreLabel(),
                        'value' => $value,
                        'code'  => $attribute->getAttributeCode(),
                    ];
                }
            }
        }
        return $data;
    }
}
