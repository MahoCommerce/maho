<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Controller_Sales_Shipment extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'sales/shipment';

    /**
     * Additional initialization
     */
    #[\Override]
    protected function _construct()
    {
        $this->setUsedModuleName('Mage_Sales');
    }

    /**
     * Init layout, menu and breadcrumb
     *
     * @return $this
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('sales/shipment')
            ->_addBreadcrumb($this->__('Sales'), $this->__('Sales'))
            ->_addBreadcrumb($this->__('Shipments'), $this->__('Shipments'));
        return $this;
    }

    /**
     * Shipments grid
     */
    public function indexAction(): void
    {
        $this->_title($this->__('Sales'))->_title($this->__('Shipments'));

        $this->_initAction()
            ->_addContent($this->getLayout()->createBlock('adminhtml/sales_shipment'))
            ->renderLayout();
    }

    /**
     * Shipment information page
     */
    public function viewAction(): void
    {
        if ($shipmentId = $this->getRequest()->getParam('shipment_id')) {
            $this->_forward('view', 'sales_order_shipment', null, ['come_from' => 'shipment']);
        } else {
            $this->_forward('noRoute');
        }
    }

    public function pdfshipmentsAction()
    {
        $shipmentIds = $this->getRequest()->getPost('shipment_ids');
        if (!empty($shipmentIds)) {
            $shipments = Mage::getResourceModel('sales/order_shipment_collection')
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('entity_id', ['in' => $shipmentIds])
                ->load();
            $pdf = Mage::getModel('sales/order_pdf_shipment')->getPdf($shipments);

            return $this->_prepareDownloadResponse('packingslip' . Mage::app()->getLocale()->utcToStore()->format('Y-m-d_H-i-s') . '.pdf', $pdf, 'application/pdf');
        }
        $this->_redirect('*/*/');
    }

    public function printAction(): void
    {
        /** @see Mage_Adminhtml_Sales_Order_InvoiceController */
        if ($shipmentId = $this->getRequest()->getParam('invoice_id')) { // invoice_id o_0
            if ($shipment = Mage::getModel('sales/order_shipment')->load($shipmentId)) {
                $pdf = Mage::getModel('sales/order_pdf_shipment')->getPdf([$shipment]);
                $this->_prepareDownloadResponse('packingslip' . Mage::app()->getLocale()->utcToStore()->format('Y-m-d_H-i-s') . '.pdf', $pdf, 'application/pdf');
            }
        } else {
            $this->_forward('noRoute');
        }
    }
}
