<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Creditmemo_Create extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'order_id';
        $this->_controller = 'sales_order_creditmemo';
        $this->_mode = 'create';

        parent::__construct();

        $this->_removeButton('delete');
        $this->_removeButton('save');
    }

    /**
     * Retrieve creditmemo model instance
     *
     * @return Mage_Sales_Model_Order_Creditmemo
     */
    public function getCreditmemo()
    {
        return Mage::registry('current_creditmemo');
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        if ($this->getCreditmemo()->getInvoice()) {
            $header = Mage::helper('sales')->__(
                'New Credit Memo for Invoice #%s',
                $this->escapeHtml($this->getCreditmemo()->getInvoice()->getIncrementId()),
            );
        } else {
            $header = Mage::helper('sales')->__(
                'New Credit Memo for Order #%s',
                $this->escapeHtml($this->getCreditmemo()->getOrder()->getRealOrderId()),
            );
        }

        return $header;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getBackUrl()
    {
        return $this->getUrl('*/sales_order/view', ['order_id' => $this->getCreditmemo()->getOrderId()]);
    }
}
