<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Resource_Product_Attribute_Backend_Tierprice extends Mage_Catalog_Model_Resource_Product_Attribute_Backend_Groupprice_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/product_attribute_tier_price', 'value_id');
    }

    /**
     * Add qty column
     *
     * @param array $columns
     * @return array
     */
    #[\Override]
    protected function _loadPriceDataColumns($columns)
    {
        $columns = parent::_loadPriceDataColumns($columns);
        $columns['price_qty'] = 'qty';
        return $columns;
    }

    /**
     * Order by qty
     *
     * @param Maho\Db\Select $select
     * @return Maho\Db\Select
     */
    #[\Override]
    protected function _loadPriceDataSelect($select)
    {
        $select->order('qty');
        return $select;
    }

    /**
     * Load product tier prices
     *
     * @deprecated since 1.3.2.3
     *
     * @param Mage_Catalog_Model_Product $product
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @return array
     */
    public function loadProductPrices($product, $attribute)
    {
        $websiteId = null;
        if ($attribute->isScopeGlobal()) {
            $websiteId = 0;
        } elseif ($product->getStoreId()) {
            $websiteId = Mage::app()->getStore($product->getStoreId())->getWebsiteId();
        }

        return $this->loadPriceData($product->getId(), $websiteId);
    }

    /**
     * Delete product tier price data from storage
     *
     * @deprecated since 1.3.2.3
     *
     * @param Mage_Catalog_Model_Product $product
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @return $this
     */
    public function deleteProductPrices($product, $attribute)
    {
        $websiteId = null;
        if (!$attribute->isScopeGlobal()) {
            $storeId = $product->getProductId();
            if ($storeId) {
                $websiteId = Mage::app()->getStore($storeId)->getWebsiteId();
            }
        }

        $this->deletePriceData($product->getId(), $websiteId);

        return $this;
    }

    /**
     * Insert product Tier Price to storage
     *
     * @deprecated since 1.3.2.3
     *
     * @param Mage_Catalog_Model_Product $product
     * @param array $data
     * @return $this
     */
    public function insertProductPrice($product, $data)
    {
        $priceObject = new Varien_Object($data);
        $priceObject->setEntityId($product->getId());

        return $this->savePriceData($priceObject);
    }
}
