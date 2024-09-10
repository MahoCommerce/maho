<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_CatalogInventory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Product stock qty block for grouped product type
 *
 * @category   Mage
 * @package    Mage_CatalogInventory
 */
class Mage_CatalogInventory_Block_Stockqty_Type_Grouped extends Mage_CatalogInventory_Block_Stockqty_Composite
{
    /**
     * Retrieve child products
     *
     * @return array
     */
    #[\Override]
    protected function _getChildProducts()
    {
        /** @var Mage_Catalog_Model_Product_Type_Grouped $productType */
        $productType = $this->_getProduct()->getTypeInstance(true);
        return $productType->getAssociatedProducts($this->_getProduct());
    }
}
