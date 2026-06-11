<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Search extends Mage_Adminhtml_Block_Sales_Order_Create_Abstract
{
    /**
     * Contains button descriptions to be shown at the top of accordion
     * @var list<array>
     */
    protected $_buttons = [];

    public function __construct()
    {
        parent::__construct();
        $this->setId('sales_order_create_search');
        $this->addButton([
            'label' => Mage::helper('sales')->__('Add Selected Product(s) to Order'),
            'onclick' => 'order.productGridAddSelected()',
            'class' => 'add',
        ]);
        $this->addButton([
            'label' => Mage::helper('sales')->__('Cancel'),
            'onclick' => 'order.productGridHide()',
        ]);
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        return Mage::helper('sales')->__('Please Select Products to Add');
    }

    /**
     * Add button to the items header
     *
     * @param array $args
     */
    public function addButton($args)
    {
        $this->_buttons[] = $args;
    }

    /**
     * Render buttons and return HTML code
     *
     * @return string
     */
    public function getButtonsHtml()
    {
        $html = '';
        // Make buttons to be rendered in opposite order of addition. This makes "Add products" the last one.
        $this->_buttons = array_reverse($this->_buttons);
        foreach ($this->_buttons as $buttonData) {
            $html .= $this->getLayout()->createBlock('adminhtml/widget_button')->setData($buttonData)->toHtml();
        }

        return $html;
    }

    /**
     * @return string
     */
    public function getHeaderCssClass()
    {
        return 'head-catalog-product';
    }
}
