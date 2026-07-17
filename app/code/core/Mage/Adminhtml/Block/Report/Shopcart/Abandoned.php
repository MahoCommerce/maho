<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Report_Shopcart_Abandoned extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'report_shopcart_abandoned';
        $this->_headerText = Mage::helper('reports')->__('Abandoned carts');
        parent::__construct();
        $this->_removeButton('add');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'store_switcher',
            $this->getLayout()->createBlock('adminhtml/store_switcher')
                ->setUseConfirm(false)
                ->setSwitchUrl($this->getUrl('*/*/*', ['store' => null]))
                ->setTemplate('report/store/switcher.phtml'),
        );
        return parent::_prepareLayout();
    }

    public function getStoreSwitcherHtml()
    {
        return Mage::app()->isSingleStoreMode() ? '' : $this->getChildHtml('store_switcher');
    }

    #[\Override]
    public function getGridHtml()
    {
        return $this->getStoreSwitcherHtml() . parent::getGridHtml();
    }
}
