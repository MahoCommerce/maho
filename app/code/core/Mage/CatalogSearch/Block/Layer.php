<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogSearch
 */

class Mage_CatalogSearch_Block_Layer extends Mage_Catalog_Block_Layer_View
{
    /**
     * Internal constructor
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        Mage::register('current_layer', $this->getLayer(), true);
    }

    /**
     * Initialize blocks names
     */
    #[\Override]
    protected function _initBlocks()
    {
        parent::_initBlocks();

        $this->_attributeFilterBlockName = 'catalogsearch/layer_filter_attribute';
    }

    /**
     * Get layer object
     *
     * @return Mage_Catalog_Model_Layer
     */
    #[\Override]
    public function getLayer()
    {
        return Mage::getSingleton('catalogsearch/layer');
    }

    /**
     * Check availability display layer block
     *
     * @return bool
     */
    #[\Override]
    public function canShowBlock()
    {
        $_isLNAllowedByEngine = Mage::helper('catalogsearch')->getEngine()->isLeyeredNavigationAllowed();
        if (!$_isLNAllowedByEngine) {
            return false;
        }
        $availableResCount = (int) Mage::app()->getStore()
            ->getConfig(Mage_CatalogSearch_Model_Layer::XML_PATH_DISPLAY_LAYER_COUNT);

        if (!$availableResCount
            || ($availableResCount > $this->getLayer()->getProductCollection()->getSize())
        ) {
            return parent::canShowBlock();
        }
        return false;
    }
}
