<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Report_Product_Viewed extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'report_product_viewed';
        $this->_headerText = Mage::helper('reports')->__('Most Viewed');
        parent::__construct();
        $this->setTemplate('report/grid/container.phtml');
        $this->_removeButton('add');
        $this->addButton('filter_form_submit', [
            'label'     => Mage::helper('reports')->__('Show Report'),
            'onclick'   => 'filterFormSubmit()',
        ]);
    }

    public function getFilterUrl()
    {
        $this->getRequest()->setParam('filter', null);
        return $this->getUrl('*/*/viewed', ['_current' => true]);
    }
}
