<?php

/**
 * Maho
 *
 * @package    Mage_Bundle
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Bundle Selection Model
 *
 * @package    Mage_Bundle
 *
 * @method Mage_Bundle_Model_Resource_Selection _getResource()
 * @method Mage_Bundle_Model_Resource_Selection getResource()
 * @method Mage_Bundle_Model_Resource_Selection_Collection getCollection()
 *
 * @method string getDefaultPriceScope()
 * @method int getIsDefault()
 * @method $this setIsDefault(int $value)
 * @method int getOptionId()
 * @method $this setOptionId(int $value)
 * @method int getParentProductId()
 * @method $this setParentProductId(int $value)
 * @method int getPosition()
 * @method $this setPosition(int $value)
 * @method int getProductId()
 * @method $this setProductId(int $value)
 * @method int getSelectionCanChangeQty()
 * @method $this setSelectionCanChangeQty(int $value)
 * @method int getSelectionId()
 * @method int getSelectionPriceType()
 * @method $this setSelectionPriceType(int $value)
 * @method float getSelectionPriceValue()
 * @method $this setSelectionPriceValue(float $value)
 * @method float getSelectionQty()
 * @method $this setSelectionQty(float $value)
 * @method int getWebsiteId()
 * @method $this setWebsiteId(int $value)
 * @method $this unsSelectionPriceValue()
 * @method $this unsSelectionPriceType()
 * @method bool isSalable()
 */
class Mage_Bundle_Model_Selection extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('bundle/selection');
        parent::_construct();
    }

    /**
     * @throws Mage_Core_Model_Store_Exception
     */
    #[\Override]
    protected function _afterSave()
    {
        $storeId = Mage::registry('product')->getStoreId();
        if (!Mage::helper('catalog')->isPriceGlobal() && $storeId) {
            $this->setWebsiteId(Mage::app()->getStore($storeId)->getWebsiteId());
            $this->getResource()->saveSelectionPrice($this);

            if (!$this->getDefaultPriceScope()) {
                $this->unsSelectionPriceValue();
                $this->unsSelectionPriceType();
            }
        }
        return parent::_afterSave();
    }
}
