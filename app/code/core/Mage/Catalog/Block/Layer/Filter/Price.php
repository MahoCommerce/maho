<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Block_Layer_Filter_Price extends Mage_Catalog_Block_Layer_Filter_Abstract
{
    /**
     * Initialize Price filter module
     */
    public function __construct()
    {
        parent::__construct();

        $this->_filterModelName = 'catalog/layer_filter_price';

        if ($this->isInputMode()) {
            $this->setTemplate('catalog/layer/filter/price.phtml');
        }
    }

    /**
     * Check if input mode is enabled
     */
    public function isInputMode(): bool
    {
        return Mage::getStoreConfig(Mage_Catalog_Model_Layer_Filter_Price::XML_PATH_RANGE_CALCULATION)
            === Mage_Catalog_Model_Layer_Filter_Price::RANGE_CALCULATION_INPUT;
    }

    /**
     * Get minimum price from collection
     */
    public function getMinPrice(): float
    {
        return (float) $this->getLayer()->getProductCollection()->getMinPrice();
    }

    /**
     * Get maximum price from collection
     */
    public function getMaxPrice(): float
    {
        return (float) $this->getLayer()->getProductCollection()->getMaxPrice();
    }

    /**
     * Get current filter URL with price placeholder
     */
    public function getFilterUrl(): string
    {
        $query = [
            $this->_filter->getRequestVar() => '__PRICE_RANGE__',
            Mage::getBlockSingleton('page/html_pager')->getPageVarName() => null,
        ];
        return Mage::getUrl('*/*/*', ['_current' => true, '_use_rewrite' => true, '_query' => $query]);
    }

    /**
     * Get currency symbol
     */
    public function getCurrencySymbol(): string
    {
        return Mage::app()->getLocale()->getCurrencySymbol(Mage::app()->getStore()->getCurrentCurrencyCode());
    }

    /**
     * Prepare filter process
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareFilter()
    {
        $this->_filter->setAttributeModel($this->getAttributeModel());
        return $this;
    }
}
