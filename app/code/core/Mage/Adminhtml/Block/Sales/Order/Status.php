<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Status extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->_controller = 'sales_order_status';
        $this->_headerText = Mage::helper('sales')->__('Order Statuses');
        $this->_addButtonLabel = Mage::helper('sales')->__('Create New Status');
        $this->_addButton('assign', [
            'label'     => Mage::helper('sales')->__('Assign Status to State'),
            'onclick'   => Mage::helper('core/js')->getSetLocationJs($this->getAssignUrl()),
            'class'     => 'add',
        ]);
        parent::__construct();
    }

    /**
     * Create url getter
     *
     * @return string
     */
    #[\Override]
    public function getCreateUrl()
    {
        return $this->getUrl('*/sales_order_status/new');
    }

    /**
     * Assign url getter
     *
     * @return string
     */
    public function getAssignUrl()
    {
        return $this->getUrl('*/sales_order_status/assign');
    }
}
