<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Catalog_Product_Composite_Fieldset_Configurable extends Mage_Catalog_Block_Product_View_Type_Configurable
{
    /**
     * Retrieve product
     *
     * @return Mage_Catalog_Model_Product
     */
    #[\Override]
    public function getProduct()
    {
        if (!$this->hasData('product')) {
            $this->setData('product', Mage::registry('product'));
        }
        $product = $this->getData('product');
        if (is_null($product->getTypeInstance(true)->getStoreFilter($product))) {
            $product->getTypeInstance(true)->setStoreFilter(Mage::app()->getStore($product->getStoreId()), $product);
        }

        return $product;
    }

    /**
     * Retrieve current store
     *
     * @return Mage_Core_Model_Store
     */
    #[\Override]
    public function getCurrentStore()
    {
        return Mage::app()->getStore($this->getProduct()->getStoreId());
    }

    /**
     * Returns additional values for js config, con be overridden by descendants
     *
     * @return array
     */
    #[\Override]
    protected function _getAdditionalConfig()
    {
        $result = parent::_getAdditionalConfig();
        $result['disablePriceReload'] = true; // There's no field for price at popup
        $result['stablePrices'] = true; // We don't want to recalc prices displayed in OPTIONs of SELECT
        return $result;
    }
}
