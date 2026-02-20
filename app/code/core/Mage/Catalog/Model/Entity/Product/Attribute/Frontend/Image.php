<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Entity_Product_Attribute_Frontend_Image extends Mage_Eav_Model_Entity_Attribute_Frontend_Abstract
{
    /**
     * @param \Maho\DataObject $object
     * @param string $size
     * @return bool|string
     */
    public function getUrl($object, $size = null)
    {
        $url = false;
        $image = $object->getData($this->getAttribute()->getAttributeCode());

        if (!is_null($size) && file_exists(Mage::getBaseDir('media') . '/catalog/product/' . $size . '/' . $image)) {
            // image is cached
            $url = Mage::getBaseUrl('media') . 'catalog/product/' . $size . '/' . $image;
        } elseif (!is_null($size)) {
            // image is not cached
            $url = Mage::getBaseUrl() . 'catalog/product/image/size/' . $size . '/' . $image;
        } else {
            // image is not cached
            $url = Mage::getBaseUrl() . 'catalog/product/image' . $image;
        }
        return $url;
    }
}
