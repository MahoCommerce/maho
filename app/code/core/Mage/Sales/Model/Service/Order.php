<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * Builds the invoices, the shipments and the credit memos of one order through Mage_Sales_Service_Order_Document.
 * When a store rewrites this model, the service builds each document through the rewrite. New code calls
 * buildInvoice(), buildShipment() and buildCreditmemo() of the service, and does not create this model.
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
            $data = $this->getDocumentService()->parseLocaleNumbers($data);
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
        $qtys = (array) $qtys;
        $this->updateLocaleNumbers($qtys);

        return $this->getDocumentService()->buildInvoiceWith($this->_order, $qtys, $this->_convertor, $this->_canInvoiceItem(...));
    }

    /**
     * @param array $qtys
     * @return Mage_Sales_Model_Order_Shipment
     */
    public function prepareShipment($qtys = [])
    {
        $qtys = (array) $qtys;
        $this->updateLocaleNumbers($qtys);

        return $this->getDocumentService()->buildShipmentWith($this->_order, $qtys, $this->_convertor, $this->_canShipItem(...));
    }

    /**
     * @param array $data
     * @return Mage_Sales_Model_Order_Creditmemo
     */
    public function prepareCreditmemo($data = [])
    {
        return $this->_buildCreditmemo(null, (array) $data);
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param array $data
     * @return Mage_Sales_Model_Order_Creditmemo
     */
    public function prepareInvoiceCreditmemo($invoice, $data = [])
    {
        return $this->_buildCreditmemo($invoice, (array) $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function _buildCreditmemo(?Mage_Sales_Model_Order_Invoice $invoice, array $data): Mage_Sales_Model_Order_Creditmemo
    {
        if (isset($data['qtys']) && is_array($data['qtys'])) {
            $this->updateLocaleNumbers($data['qtys']);
        }

        return $this->getDocumentService()->buildCreditmemoWith(
            $this->_order,
            $data,
            $invoice,
            $this->_convertor,
            $this->_canRefundItem(...),
            $this->_initCreditmemoData(...),
        );
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @param array $data
     * @return void
     */
    protected function _initCreditmemoData($creditmemo, $data)
    {
        $this->getDocumentService()->setCreditmemoAmounts($creditmemo, (array) $data);
    }

    /**
     * @param Mage_Sales_Model_Order_Item $item
     * @param array $qtys
     * @return bool
     */
    protected function _canInvoiceItem($item, $qtys = [])
    {
        return $this->getDocumentService()->canInvoiceItem($item, (array) $qtys);
    }

    /**
     * @param Mage_Sales_Model_Order_Item $item
     * @param array $qtys
     * @return bool
     */
    protected function _canShipItem($item, $qtys = [])
    {
        return $this->getDocumentService()->canShipItem($item, (array) $qtys);
    }

    /**
     * @param Mage_Sales_Model_Order_Item $item
     * @param array $qtys
     * @param array $invoiceQtysRefundLimits
     * @return bool
     */
    protected function _canRefundItem($item, $qtys = [], $invoiceQtysRefundLimits = [])
    {
        return $this->getDocumentService()->canRefundItem($item, (array) $qtys, (array) $invoiceQtysRefundLimits);
    }

    /**
     * @param Mage_Sales_Model_Order_Item $item
     * @param array $invoiceQtysRefundLimits
     * @return bool
     */
    protected function _canRefundNoDummyItem($item, $invoiceQtysRefundLimits = [])
    {
        return $this->getDocumentService()->hasQtyToRefund($item, (array) $invoiceQtysRefundLimits);
    }

    private function getDocumentService(): Mage_Sales_Service_Order_Document
    {
        return Mage::getService('sales/order_document');
    }
}
