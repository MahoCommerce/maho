<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Edit_Action_Attribute_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();

        $this->setId('attributes_update_tabs');
        $this->setDestElementId('attributes_edit_form');
        $this->setTitle(Mage::helper('catalog')->__('Products Information'));
    }
}
