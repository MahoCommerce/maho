<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
