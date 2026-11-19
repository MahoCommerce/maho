<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Sales_Order_InvoiceController extends Mage_Adminhtml_Controller_Sales_Invoice
{
    /**
     * Get requested items qty's from request
     */
    protected function _getItemQtys(): array
    {
        $data = $this->getRequest()->getParam('invoice');
        return (array) ($data['items'] ?? []);
    }

    /**
     * Initialize invoice model instance
     *
     * @throws Mage_Core_Exception
     */
    protected function _initInvoice(bool $update = false): Mage_Sales_Model_Order_Invoice|false
    {
        $this->_title($this->__('Sales'))->_title($this->__('Invoices'));

        $service = Mage::getService('sales/order_document');
        $invoiceId = (int) $this->getRequest()->getParam('invoice_id');
        // _isAllowed() checks the same for each action. This check also covers the other callers.
        if (!$invoiceId && !$this->_isCreateAllowed()) {
            $this->_getSession()->addError($this->__('Access denied.'));
            return false;
        }

        try {
            $invoice = $invoiceId
                ? $service->requireInvoice($invoiceId)
                : $service->prepareInvoice(Mage::getService('sales/order')->getById((int) $this->getRequest()->getParam('order_id')), $this->_getItemQtys());
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
        $data = $this->_filterInvoiceData((array) $this->getRequest()->getPost('invoice'));
        $orderId = $this->getRequest()->getParam('order_id');

        if (!empty($data['comment_text'])) {
            Mage::getSingleton('adminhtml/session')->setCommentText($data['comment_text']);
        }

        try {
            $service = Mage::getService('sales/order_document');
            $tracks = [];
            foreach ((array) $this->getRequest()->getPost('tracking') as $track) {
                $tracks[] = Mage::getModel('sales/order_shipment_track')->addData($track);
            }
            $invoice = $service->invoice(
                Mage::getService('sales/order')->getById((int) $orderId),
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
            if ($invoice->getData(Mage_Sales_Service_Order_Document::EMAIL_FAILED)) {
                $this->_getSession()->addError($this->__('Unable to send the invoice email.'));
            }
            if ($invoice->getData(Mage_Sales_Service_Order_Document::SHIPMENT_EMAIL_FAILED)) {
                $this->_getSession()->addError($this->__('Unable to send the shipment email.'));
            }
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
     * Run $method (captureInvoice, cancelInvoice or voidInvoice) of the document service on the invoice of the request.
     */
    protected function _changeInvoice(string $method, string $success, string $failure): void
    {
        if ($invoice = $this->_initInvoice()) {
            try {
                Mage::getService('sales/order_document')->{$method}($invoice);
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
            $invoiceId = (int) $this->getRequest()->getParam('id');
            $this->getRequest()->setParam('invoice_id', $invoiceId);
            $data = (array) $this->getRequest()->getPost('comment');
            // Without an invoice ID, _initInvoice() prepares a new invoice from the order
            $invoice = ($invoiceId > 0 ? $this->_initInvoice() : false) ?: Mage::throwException($this->__('The invoice no longer exists.'));
            Mage::getService('sales/order_document')->addComment(
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

    /**
     * Check the order action of each change on top of the Invoices permission.
     *
     * Without an invoice ID, an action prepares a new invoice from the order, so it needs the
     * Invoice order action. addComment reads the invoice ID from its id parameter. A void also
     * cancels the invoice, so it needs Cancel.
     */
    #[\Override]
    protected function _isAllowed(): bool
    {
        if (!parent::_isAllowed()) {
            return false;
        }

        $request = $this->getRequest();
        $action = strtolower($request->getActionName());
        $aclResource = match ($action) {
            'start', 'new', 'updateqty', 'save' => 'sales/order/actions/invoice',
            'capture' => 'sales/order/actions/capture',
            'cancel', 'void' => 'sales/order/actions/cancel',
            default => null,
        };
        if ($aclResource !== null && !Mage::getSingleton('admin/session')->isAllowed($aclResource)) {
            return false;
        }

        $invoiceId = (int) $request->getParam($action === 'addcomment' ? 'id' : 'invoice_id');
        if ($invoiceId === 0 && (int) $request->getParam('order_id') > 0) {
            return $this->_isCreateAllowed();
        }

        return true;
    }

    /**
     * Return true when the admin may create an invoice.
     */
    protected function _isCreateAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/invoice');
    }

    /**
     * Remove the invoice options that the admin may not use.
     *
     * The invoice form shows the capture choice only with the Capture permission, and the
     * Create Shipment option only with the Ship permission.
     *
     * @param array<string, mixed> $data the posted invoice data
     * @return array<string, mixed>
     */
    protected function _filterInvoiceData(array $data): array
    {
        $session = Mage::getSingleton('admin/session');
        if (!$session->isAllowed('sales/order/actions/capture')) {
            unset($data['capture_case']);
        }

        if (!$session->isAllowed('sales/order/actions/ship')) {
            unset($data['do_shipment']);
        }

        return $data;
    }
}
