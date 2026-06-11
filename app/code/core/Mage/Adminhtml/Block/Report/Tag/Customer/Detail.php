<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Report_Tag_Customer_Detail extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'report_tag_customer_detail';
        $customer = Mage::getModel('customer/customer')->load($this->getRequest()->getParam('id'));
        $customerName = $this->escapeHtml($customer->getName());
        $this->_headerText = Mage::helper('reports')->__('Tags Submitted by %s', $customerName);
        parent::__construct();
        $this->_removeButton('add');
        $this->setBackUrl($this->getUrl('*/report_tag/customer/'));
        $this->_addBackButton();
    }
}
