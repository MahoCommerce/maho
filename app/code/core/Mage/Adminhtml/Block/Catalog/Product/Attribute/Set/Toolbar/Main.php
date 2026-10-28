<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Attribute_Set_Toolbar_Main extends Mage_Eav_Block_Adminhtml_Attribute_Set_Toolbar_Main
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('catalog/product/attribute/set/toolbar/main.phtml');
    }

    #[\Override]
    protected function _getHeader()
    {
        return Mage::helper('catalog')->__('Manage Attribute Sets');
    }

    /**
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        Mage::dispatchEvent('adminhtml_catalog_product_attribute_set_toolbar_main_html_before', ['block' => $this]);
        return parent::_toHtml();
    }
}
