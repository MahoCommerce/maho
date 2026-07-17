<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Edit_Tabs_Grouped extends Mage_Adminhtml_Block_Catalog_Product_Edit_Tabs
{
    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $this->addTab('super', [
            'label'     => Mage::helper('catalog')->__('Associated Products'),
            'url'       => $this->getUrl('*/*/superGroup', ['_current' => true]),
            'class'     => 'ajax',
        ]);
        return $this;
    }
}
