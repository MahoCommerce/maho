<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Sales_Model_Resource_Quote_Item_Option _getResource()
 * @method Mage_Sales_Model_Resource_Quote_Item_Option getResource()
 * @method Mage_Sales_Model_Resource_Quote_Item_Option_Collection getCollection()
 *
 * @method $this setBackorders(float $value)
 * @method $this setHasError(bool $value)
 * @method $this setHasQtyOptionUpdate(bool $value)
 * @method int getItemId()
 * @method $this setItemId(int $value)
 * @method int getProductId()
 * @method $this setMessage(string $value)
 * @method $this setProductId(int $value)
 * @method $this setIsQtyDecimal(bool $value)
 * @method string getCode()
 * @method $this setCode(string $value)
 * @method $this setValue(string $value)
 */
class Mage_Sales_Model_Quote_Item_Option extends Mage_Core_Model_Abstract implements Mage_Catalog_Model_Product_Configuration_Item_Option_Interface
{
    protected $_item;
    protected $_product;

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/quote_item_option');
    }

    /**
     * Checks that item option model has data changes
     *
     * @return bool
     */
    #[\Override]
    protected function _hasModelChanged()
    {
        if (!$this->hasDataChanges()) {
            return false;
        }

        return $this->_getResource()->hasDataChanged($this);
    }

    /**
     * Set quote item
     *
     * @param   Mage_Sales_Model_Quote_Item $item
     * @return  $this
     */
    public function setItem($item)
    {
        $this->_item = $item;
        if ($this->getItemId() != $item->getId()) {
            $this->setItemId($item->getId());
        }
        return $this;
    }

    /**
     * Get option item
     *
     * @return Mage_Sales_Model_Quote_Item
     */
    public function getItem()
    {
        return $this->_item;
    }

    /**
     * Set option product
     *
     * @param   Mage_Catalog_Model_Product $product
     * @return  $this
     */
    public function setProduct($product)
    {
        $this->_product = $product;
        if ($this->getProductId() != $product->getId()) {
            $this->setProductId($product->getId());
        }
        return $this;
    }

    /**
     * Get option product
     *
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct()
    {
        return $this->_product;
    }

    /**
     * Get option value
     *
     * @return mixed
     */
    #[\Override]
    public function getValue()
    {
        return $this->_getData('value');
    }

    /**
     * Initialize item identifier before save data
     */
    #[\Override]
    protected function _beforeSave()
    {
        if ($this->getItem()) {
            $this->setItemId($this->getItem()->getId());
        }
        return parent::_beforeSave();
    }

    /**
     * Clone option object
     */
    public function __clone()
    {
        $this->setId(null);
        $this->_item    = null;
    }
}
