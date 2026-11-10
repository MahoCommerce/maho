<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * Builds the invoices, the shipments and the credit memos of one order through Mage_Sales_Service_Order.
 *
 * @deprecated since 27.1, use Mage_Sales_Service_Order::buildInvoice(), buildShipment() and buildCreditmemo()
 */
class Mage_Sales_Model_Service_Order
{
    /**
     * @var Mage_Sales_Model_Order
     */
    protected $_order;

    /**
     * @var Mage_Sales_Model_Convert_Order
     */
    protected $_convertor;

    public function __construct(Mage_Sales_Model_Order $order)
    {
        $this->_order       = $order;
        $this->_convertor   = Mage::getModel('sales/convert_order');
    }

    /**
     * @return $this
     */
    public function setConvertor(Mage_Sales_Model_Convert_Order $convertor)
    {
        $this->_convertor = $convertor;
        return $this;
    }

    /**
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return $this->_order;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function updateLocaleNumbers(&$data)
    {
        if (is_array($data)) {
            $data = $this->getOrderService()->parseLocaleNumbers($data);
        }
        return $this;
    }

    /**
     * @param mixed $value
     * @return float|null
     */
    protected function _getLocaleNumber($value)
    {
        return Mage::app()->getLocale()->getNumber($value);
    }

    /**
     * @param array $qtys
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function prepareInvoice($qtys = [])
    {
        return $this->getOrderService()->buildInvoice($this->_order, (array) $qtys, $this->_convertor);
    }

    /**
     * @param array $qtys
     * @return Mage_Sales_Model_Order_Shipment
     */
    public function prepareShipment($qtys = [])
    {
        return $this->getOrderService()->buildShipment($this->_order, (array) $qtys, $this->_convertor);
    }

    /**
     * @param array $data
     * @return Mage_Sales_Model_Order_Creditmemo
     */
    public function prepareCreditmemo($data = [])
    {
        return $this->getOrderService()->buildCreditmemo($this->_order, (array) $data, null, $this->_convertor);
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param array $data
     * @return Mage_Sales_Model_Order_Creditmemo
     */
    public function prepareInvoiceCreditmemo($invoice, $data = [])
    {
        return $this->getOrderService()->buildCreditmemo($this->_order, (array) $data, $invoice, $this->_convertor);
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @param array $data
     */
    protected function _initCreditmemoData($creditmemo, $data)
    {
        $this->getOrderService()->setCreditmemoAmounts($creditmemo, (array) $data);
    }

    /**
     * @param Mage_Sales_Model_Order_Item $item
     * @param array $qtys
     * @return bool
     */
    protected function _canInvoiceItem($item, $qtys = [])
    {
        return $this->getOrderService()->canInvoiceItem($item, (array) $qtys);
    }

    /**
     * @param Mage_Sales_Model_Order_Item $item
     * @param array $qtys
     * @return bool
     */
    protected function _canShipItem($item, $qtys = [])
    {
        return $this->getOrderService()->canShipItem($item, (array) $qtys);
    }

    /**
     * @param Mage_Sales_Model_Order_Item $item
     * @param array $qtys
     * @param array $invoiceQtysRefundLimits
     * @return bool
     */
    protected function _canRefundItem($item, $qtys = [], $invoiceQtysRefundLimits = [])
    {
        return $this->getOrderService()->canRefundItem($item, (array) $qtys, (array) $invoiceQtysRefundLimits);
    }

    /**
     * @param Mage_Sales_Model_Order_Item $item
     * @param array $invoiceQtysRefundLimits
     * @return bool
     */
    protected function _canRefundNoDummyItem($item, $invoiceQtysRefundLimits = [])
    {
        return $this->getOrderService()->hasQtyToRefund($item, (array) $invoiceQtysRefundLimits);
    }

    private function getOrderService(): Mage_Sales_Service_Order
    {
        return Mage::getService('sales/order');
    }
}
