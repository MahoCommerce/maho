<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Edit_Action_Attribute_Tab_Websites extends Mage_Adminhtml_Block_Widget implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    public function getWebsiteCollection()
    {
        return Mage::app()->getWebsites();
    }

    public function getGroupCollection(Mage_Core_Model_Website $website)
    {
        return $website->getGroups();
    }

    public function getStoreCollection(Mage_Core_Model_Store_Group $group)
    {
        return $group->getStores();
    }

    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('catalog')->__('Websites');
    }

    #[\Override]
    public function getTabTitle()
    {
        return Mage::helper('catalog')->__('Websites');
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
