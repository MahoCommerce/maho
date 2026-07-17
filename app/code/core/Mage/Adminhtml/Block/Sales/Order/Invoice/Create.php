<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Invoice_Create extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'order_id';
        $this->_controller = 'sales_order_invoice';
        $this->_mode = 'create';

        parent::__construct();

        $this->_removeButton('save');
        $this->_removeButton('delete');
    }

    /**
     * Retrieve invoice model instance
     *
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function getInvoice()
    {
        return Mage::registry('current_invoice');
    }

    /**
     * Retrieve text for header
     *
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        return ($this->getInvoice()->getOrder()->getForcedDoShipmentWithInvoice())
            ? Mage::helper('sales')->__(
                'New Invoice and Shipment for Order #%s',
                $this->escapeHtml($this->getInvoice()->getOrder()->getRealOrderId()),
            )
            : Mage::helper('sales')->__(
                'New Invoice for Order #%s',
                $this->escapeHtml($this->getInvoice()->getOrder()->getRealOrderId()),
            );
    }

    /**
     * Retrieve back url
     *
     * @return string
     */
    #[\Override]
    public function getBackUrl()
    {
        return $this->getUrl('*/sales_order/view', ['order_id' => $this->getInvoice()->getOrderId()]);
    }
}
