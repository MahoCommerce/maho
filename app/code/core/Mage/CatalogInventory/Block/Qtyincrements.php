<?php

/**
 * Maho
 *
 * @package    Mage_CatalogInventory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogInventory_Block_Qtyincrements extends Mage_Core_Block_Template
{
    /**
     * Qty Increments cache
     *
     * @var float|false
     */
    protected $_qtyIncrements;

    /**
     * Retrieve current product object
     *
     * @return Mage_Catalog_Model_Product
     */
    protected function _getProduct()
    {
        return Mage::registry('current_product');
    }

    /**
     * Retrieve current product name
     *
     * @return string
     */
    public function getProductName()
    {
        return $this->_getProduct()->getName();
    }

    /**
     * Retrieve product qty increments
     *
     * @return float|false
     */
    public function getProductQtyIncrements()
    {
        if ($this->_qtyIncrements === null) {
            $this->_qtyIncrements = $this->_getProduct()->getStockItem()->getQtyIncrements();
            if (!$this->_getProduct()->isSaleable()) {
                $this->_qtyIncrements = false;
            }
        }
        return $this->_qtyIncrements;
    }

    /**
     * Retrieve block cache tags
     *
     * @return array
     */
    #[\Override]
    public function getCacheTags()
    {
        return array_merge(parent::getCacheTags(), $this->_getProduct()->getCacheIdTags());
    }
}
