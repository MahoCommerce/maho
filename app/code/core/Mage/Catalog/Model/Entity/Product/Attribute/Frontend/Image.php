<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

/**
 * @deprecated since 26.11 no attribute uses it, use Mage_Catalog_Model_Product_Attribute_Frontend_Image
 */
class Mage_Catalog_Model_Entity_Product_Attribute_Frontend_Image extends Mage_Eav_Model_Entity_Attribute_Frontend_Abstract
{
    /**
     * @param \Maho\DataObject $object
     * @param string $size
     * @return false|string
     */
    public function getUrl($object, $size = null)
    {
        return Mage::getModel('catalog/product_attribute_frontend_image')
            ->setAttribute($this->getAttribute())
            ->getUrl($object, $size);
    }
}
