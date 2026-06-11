<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Edit_Action_Attribute_Tab_Inventory extends Mage_Adminhtml_Block_Widget implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Retrieve Backorders Options
     *
     * @return array
     */
    public function getBackordersOption()
    {
        return Mage::getSingleton('cataloginventory/source_backorders')->toOptionArray();
    }

    /**
     * Retrieve field suffix
     *
     * @return string
     */
    public function getFieldSuffix()
    {
        return 'inventory';
    }

    /**
     * Retrieve current store id
     *
     * @return int
     */
    public function getStoreId()
    {
        $storeId = $this->getRequest()->getParam('store');
        return (int) $storeId;
    }

    /**
     * Get default config value
     *
     * @param string $field
     * @return mixed
     */
    public function getDefaultConfigValue($field)
    {
        return Mage::getStoreConfig(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_ITEM . $field, $this->getStoreId());
    }

    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('catalog')->__('Inventory');
    }

    #[\Override]
    public function getTabTitle()
    {
        return Mage::helper('catalog')->__('Inventory');
    }

    #[\Override]
    public function canShowTab()
    {
        return true;
    }

    #[\Override]
    public function isHidden()
    {
        return false;
    }
}
