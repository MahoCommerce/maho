<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Attribute_Set_Toolbar_Add extends Mage_Eav_Block_Adminhtml_Attribute_Set_Toolbar_Add
{
    #[\Override]
    protected function _construct()
    {
        $this->setTemplate('catalog/product/attribute/set/toolbar/add.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $this->setChild(
            'setForm',
            $this->getLayout()->createBlock('adminhtml/catalog_product_attribute_set_main_formset'),
        );
        return $this;
    }
}
