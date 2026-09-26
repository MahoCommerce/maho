<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Product_Attribute_Frontend_Image extends Mage_Eav_Model_Entity_Attribute_Frontend_Abstract
{
    /**
     * The URL of the original image, or of a resized copy when $size is set, such as "135" or "135x90"
     *
     * @param \Maho\DataObject $object
     * @param string $size
     * @return false|string
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getUrl($object, $size = null)
    {
        $attributeCode = $this->getAttribute()->getAttributeCode();
        if ($size !== null && $object instanceof Mage_Catalog_Model_Product) {
            [$width, $height] = array_pad(explode('x', strtolower((string) $size), 2), 2, '');
            return (string) Mage::helper('catalog/image')
                ->init($object, $attributeCode)
                ->resize((int) $width ?: null, (int) $height ?: null);
        }

        $image = $object->getData($attributeCode);
        if ($image) {
            return Mage::app()->getStore($object->getStore())->getBaseUrl('media') . 'catalog/product/' . ltrim((string) $image, '/');
        }
        return false;
    }
}
