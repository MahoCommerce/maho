<?php

/**
 * Maho
 *
 * @package    Mage_Wishlist
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Wishlist_Model_Config
{
    public const XML_PATH_PRODUCT_ATTRIBUTES = 'global/wishlist/item/product_attributes';

    /**
     * Get product attributes that need in wishlist
     *
     */
    public function getProductAttributes()
    {
        $attrsForCatalog  = Mage::getSingleton('catalog/config')->getProductAttributes();
        $attrsForWishlist = Mage::getConfig()->getNode(self::XML_PATH_PRODUCT_ATTRIBUTES)->asArray();

        return array_merge($attrsForCatalog, array_keys($attrsForWishlist));
    }
}
