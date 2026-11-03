<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Sales_Order_InvoiceController extends Mage_Adminhtml_Controller_Sales_Invoice
{
    /**
     * Get requested items qty's from request
     */
    protected function _getItemQtys()
    {
        $data = $this->getRequest()->getParam('invoice');
        $qtys = $data['items'] ?? [];
        return $qtys;
    }

    /**
     * Initialize invoice model instance
     *
     * @return Mage_Sales_Model_Order_Invoice|false
     * @throws Mage_Core_Exception
     */
    protected function _initInvoice($update = false)
    {
        $this->_title($this->__('Sales'))->_title($this->__('Invoices'));

        $service = Mage::getService('sales/order');
        $invoiceId = (int) $this->getRequest()->getParam('invoice_id');
        try {
            $invoice = $invoiceId
                ? $service->requireInvoice($invoiceId)
                : $service->prepareInvoice($service->requireById((int) $this->getRequest()->getParam('order_id')), $this->_getItemQtys());
        } catch (Mage_Core_Exception_NoSuchEntity|Mage_Core_Exception_Conflict $e) {
            $this->_getSession()->addError($e->getMessage());
            return false;
        }

        Mage::register('current_invoice', $invoice);
        return $invoice;
    }

    /**
     * Invoice information page
     */
    #[\Override]
    #[Maho\Config\Route('/admin/sales_order_invoice/view')]
    public function viewAction(): void
    {
        $invoice = $this->_initInvoice();
        if ($invoice) {
            $this->_title(sprintf('#%s', $invoice->getIncrementId()));

            $this->loadLayout()
                ->_setActiveMenu('sales/invoice');

            /** @var Mage_Adminhtml_Block_Sales_Order_Invoice_View $block */
            $block = $this->getLayout()->getBlock('sales_invoice_view');
            $block->updateBackButtonUrl($this->getRequest()->getParam('come_from'));

            $this->renderLayout();
        } else {
            $this->_forward('noRoute');
        }
    }

    /**
     * Start create invoice action
     */
    #[Maho\Config\Route('/admin/sales_order_invoice/start')]
    public function startAction(): void
    {
        /**
         * Clear old values for invoice qty's
         */
        $this->_getSession()->getInvoiceItemQtys(true);
        $this->_redirect('*/*/new', ['order_id' => $this->getRequest()->getParam('order_id')]);
    }

    /**
     * Invoice create page
     */
    #[Maho\Config\Route('/admin/sales_order_invoice/new')]
    public function newAction(): void
    {
        $invoice = $this->_initInvoice();
        if ($invoice) {
            $this->_title($this->__('New Invoice'));

            if ($comment = Mage::getSingleton('adminhtml/session')->getCommentText(true)) {
                $invoice->setCommentText($comment);
            }

            $this->loadLayout()
                ->_setActiveMenu('sales/invoice')
                ->renderLayout();
        } else {
            $this->_redirect('*/sales_order/view', ['order_id' => $this->getRequest()->getParam('order_id')]);
        }
    }

    /**
     * Update items qty action
     */
    #[Maho\Config\Route('/admin/sales_order_invoice/updateQty')]
    public function updateQtyAction(): void
    {
        try {
            $invoice = $this->_initInvoice(true);
            // Save invoice comment text in current invoice object in order to display it in corresponding view
            $invoiceRawData = $this->getRequest()->getParam('invoice');
            $invoiceRawCommentText = $invoiceRawData['comment_text'];
            $invoice->setCommentText($invoiceRawCommentText);

            $this->loadLayout();
            $response = $this->getLayout()->getBlock('order_items')->toHtml();
        } catch (Mage_Core_Exception $e) {
            $response = [
                'error'     => true,
                'message'   => $e->getMessage(),
            ];
            $response = Mage::helper('core')->jsonEncode($response);
        } catch (Exception) {
            $response = [
                'error'     => true,
                'message'   => $this->__('Cannot update item quantity.'),
            ];
            $response = Mage::helper('core')->jsonEncode($response);
        }
        $this->getResponse()->setBody($response);
    }

    /**
     * Save invoice
     * We can save only new invoice. Existing invoices are not editable
     */
    #[Maho\Config\Route('/admin/sales_order_invoice/save')]
    public function saveAction(): void
    {
        $data = (array) $this->getRequest()->getPost('invoice');
        $orderId = $this->getRequest()->getParam('order_id');

        if (!empty($data['comment_text'])) {
            Mage::getSingleton('adminhtml/session')->setCommentText($data['comment_text']);
        }

        try {
            $service = Mage::getService('sales/order');
            $tracks = [];
            foreach ((array) $this->getRequest()->getPost('tracking') as $track) {
                $tracks[] = Mage::getModel('sales/order_shipment_track')->addData($track);
            }
            $service->invoice(
                $service->requireById((int) $orderId),
                $this->_getItemQtys(),
                ($data['capture_case'] ?? '') !== '' ? (string) $data['capture_case'] : null,
                ($data['comment_text'] ?? '') !== '' ? (string) $data['comment_text'] : null,
                isset($data['comment_customer_notify']),
                isset($data['is_visible_on_front']),
                !empty($data['send_email']),
                !empty($data['do_shipment']),
                $tracks,
            );

            $this->_getSession()->addSuccess(!empty($data['do_shipment'])
                ? $this->__('The invoice and shipment have been created.')
                : $this->__('The invoice has been created.'));
            Mage::getSingleton('adminhtml/session')->getCommentText(true);
            $this->_redirect('*/sales_order/view', ['order_id' => $orderId]);
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Unable to save the invoice.'));
            Mage::logException($e);
        }
        $this->_redirect('*/*/new', ['order_id' => $orderId]);
    }

    /**
     * Capture invoice action
     */
    #[Maho\Config\Route('/admin/sales_order_invoice/capture')]
    public function captureAction(): void
    {
        $this->_changeInvoice('captureInvoice', $this->__('The invoice has been captured.'), $this->__('Invoice capturing error.'));
    }

    /**
     * Cancel invoice action
     */
    #[Maho\Config\Route('/admin/sales_order_invoice/cancel')]
    public function cancelAction(): void
    {
        $this->_changeInvoice('cancelInvoice', $this->__('The invoice has been canceled.'), $this->__('Invoice canceling error.'));
    }

    /**
     * Void invoice action
     */
    #[Maho\Config\Route('/admin/sales_order_invoice/void')]
    public function voidAction(): void
    {
        $this->_changeInvoice('voidInvoice', $this->__('The invoice has been voided.'), $this->__('Invoice voiding error.'));
    }

    /**
     * Run $method (captureInvoice, cancelInvoice or voidInvoice) of the order service on the invoice of the request.
     */
    protected function _changeInvoice(string $method, string $success, string $failure): void
    {
        if ($invoice = $this->_initInvoice()) {
            try {
                Mage::getService('sales/order')->{$method}($invoice);
                $this->_getSession()->addSuccess($success);
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addError($failure);
                Mage::logException($e);
            }
            $this->_redirect('*/*/view', ['invoice_id' => $invoice->getId()]);
        } else {
            $this->_forward('noRoute');
        }
    }

    #[Maho\Config\Route('/admin/sales_order_invoice/addComment')]
    public function addCommentAction(): void
    {
        try {
            $this->getRequest()->setParam('invoice_id', $this->getRequest()->getParam('id'));
            $data = (array) $this->getRequest()->getPost('comment');
            $invoice = $this->_initInvoice() ?: Mage::throwException($this->__('The invoice no longer exists.'));
            Mage::getService('sales/order')->addComment(
                $invoice,
                (string) ($data['comment'] ?? ''),
                !empty($data['is_customer_notified']),
                isset($data['is_visible_on_front']),
            );

            $this->loadLayout();
            $response = $this->getLayout()->getBlock('invoice_comments')->toHtml();
        } catch (Mage_Core_Exception $e) {
            $response = Mage::helper('core')->jsonEncode(['error' => true, 'message' => $e->getMessage()]);
        } catch (Exception $e) {
            Mage::logException($e);
            $response = Mage::helper('core')->jsonEncode(['error' => true, 'message' => $this->__('Cannot add new comment.')]);
        }
        $this->getResponse()->setBody($response);
    }

    /**
     * Create pdf for current invoice
     */
    #[\Override]
    #[Maho\Config\Route('/admin/sales_order_invoice/print')]
    public function printAction(): void
    {
        $this->_initInvoice();
        parent::printAction();
    }
}
