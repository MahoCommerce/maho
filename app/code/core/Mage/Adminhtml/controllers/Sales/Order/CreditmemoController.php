<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Sales_Order_CreditmemoController extends Mage_Adminhtml_Controller_Sales_Creditmemo
{
    /**
     * Get requested items qtys and return to stock flags
     */
    protected function _getItemData()
    {
        $data = $this->getRequest()->getParam('creditmemo');
        if (!$data) {
            $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        }

        $qtys = $data['items'] ?? [];
        return $qtys;
    }

    /**
     * Initialize requested invoice instance
     * @param Mage_Sales_Model_Order $order
     * @return false|Mage_Sales_Model_Order_Invoice
     * @throws Mage_Core_Exception_NoSuchEntity when the order has no such invoice
     */
    protected function _initInvoice($order)
    {
        $invoiceId = (int) $this->getRequest()->getParam('invoice_id');
        return $invoiceId ? Mage::getService('sales/order_document')->requireInvoice($invoiceId, (int) $order->getId())->setOrder($order) : false;
    }

    /**
     * Return the credit memo data and the "return to stock" flags of the form, for
     * Mage_Sales_Service_Order_Document::prepareCreditmemo().
     *
     * @return array{0: array<string, mixed>, 1: array<int, bool>|null}
     */
    protected function _getCreditmemoInput(): array
    {
        $savedData = $this->_getItemData();
        $data = (array) $this->getRequest()->getParam('creditmemo');
        $data['qtys'] = [];
        $backToStock = $savedData ? [] : null;
        foreach ($savedData as $orderItemId => $itemData) {
            if (isset($itemData['qty'])) {
                $data['qtys'][$orderItemId] = $itemData['qty'];
            }
            if (isset($itemData['back_to_stock'])) {
                $backToStock[$orderItemId] = true;
            }
        }

        return [$data, $backToStock];
    }

    /**
     * Initialize creditmemo model instance
     *
     * @return Mage_Sales_Model_Order_Creditmemo|false
     * @throws Mage_Core_Exception
     */
    protected function _initCreditmemo($update = false)
    {
        $this->_title($this->__('Sales'))->_title($this->__('Credit Memos'));

        $service = Mage::getService('sales/order_document');
        $creditmemoId = (int) $this->getRequest()->getParam('creditmemo_id');
        try {
            if ($creditmemoId) {
                $creditmemo = $service->requireCreditmemo($creditmemoId);
            } else {
                $order = Mage::getService('sales/order')->getById((int) $this->getRequest()->getParam('order_id'));
                [$data, $backToStock] = $this->_getCreditmemoInput();
                $creditmemo = $service->prepareCreditmemo($order, $data, $backToStock, $this->_initInvoice($order) ?: null);
            }
        } catch (Mage_Core_Exception_NoSuchEntity|Mage_Core_Exception_Conflict $e) {
            $this->_getSession()->addError($e->getMessage());
            return false;
        }

        $args = ['creditmemo' => $creditmemo, 'request' => $this->getRequest()];
        Mage::dispatchEvent('adminhtml_sales_order_creditmemo_register_before', $args);

        Mage::register('current_creditmemo', $creditmemo);
        return $creditmemo;
    }

    /**
     * Save creditmemo and related order, invoice in one transaction
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @return $this
     * @throws Exception
     */
    #[\Deprecated('No core code calls it. Use Mage_Sales_Service_Order_Document::cancelCreditmemo().', since: '27.1')]
    protected function _saveCreditmemo($creditmemo)
    {
        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($creditmemo)
            ->addObject($creditmemo->getOrder());
        if ($creditmemo->getInvoice()) {
            $transactionSave->addObject($creditmemo->getInvoice());
        }
        $transactionSave->save();

        return $this;
    }

    /**
     * creditmemo information page
     */
    #[\Override]
    #[Maho\Config\Route('/admin/sales_order_creditmemo/view')]
    public function viewAction(): void
    {
        $creditmemo = $this->_initCreditmemo();
        if ($creditmemo) {
            $this->_title(sprintf('#%s', $creditmemo->getIncrementId()));

            $this->loadLayout();

            /** @var Mage_Adminhtml_Block_Sales_Order_Creditmemo_View $block */
            $block = $this->getLayout()->getBlock('sales_creditmemo_view');
            $block->updateBackButtonUrl($this->getRequest()->getParam('come_from'));

            $this->_setActiveMenu('sales/creditmemo')
                ->renderLayout();
        } else {
            $this->_redirect('*/*');
        }
    }

    /**
     * Start create creditmemo action
     */
    #[Maho\Config\Route('/admin/sales_order_creditmemo/start')]
    public function startAction(): void
    {
        /**
         * Clear old values for creditmemo qty's
         */
        $this->_redirect('*/*/new', ['_current' => true]);
    }

    /**
     * creditmemo create page
     */
    #[Maho\Config\Route('/admin/sales_order_creditmemo/new')]
    public function newAction(): void
    {
        if ($creditmemo = $this->_initCreditmemo()) {
            if ($creditmemo->getInvoice()) {
                $this->_title($this->__('New Memo for #%s', $creditmemo->getInvoice()->getIncrementId()));
            } else {
                $this->_title($this->__('New Memo'));
            }

            if ($comment = Mage::getSingleton('adminhtml/session')->getCommentText(true)) {
                $creditmemo->setCommentText($comment);
            }

            $this->loadLayout()
                ->_setActiveMenu('sales/creditmemo')
                ->renderLayout();
        } else {
            $this->_forward('noRoute');
        }
    }

    /**
     * Update items qty action
     */
    #[Maho\Config\Route('/admin/sales_order_creditmemo/updateQty')]
    public function updateQtyAction(): void
    {
        try {
            $this->_initCreditmemo(true);
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
                'message'   => $this->__('Cannot update the item\'s quantity.'),
            ];
            $response = Mage::helper('core')->jsonEncode($response);
        }
        $this->getResponse()->setBody($response);
    }

    /**
     * Save creditmemo
     * We can save only new creditmemo. Existing creditmemos are not editable
     */
    #[Maho\Config\Route('/admin/sales_order_creditmemo/save')]
    public function saveAction(): void
    {
        $data = (array) $this->getRequest()->getPost('creditmemo');
        if (!empty($data['comment_text'])) {
            Mage::getSingleton('adminhtml/session')->setCommentText($data['comment_text']);
        }

        $orderId = (int) $this->getRequest()->getParam('order_id');
        try {
            if ($this->getRequest()->getParam('creditmemo_id')) {
                $this->_forward('noRoute');
                return;
            }
            $service = Mage::getService('sales/order_document');
            $order = Mage::getService('sales/order')->getById($orderId);
            [$input, $backToStock] = $this->_getCreditmemoInput();
            if ($input['qtys'] === []) {
                // Without qtys, the service refunds every item, as the form without item rows does
                unset($input['qtys']);
            }
            // The service prepares the credit memo under the order lock, so the event runs there
            $service->refund(
                $order,
                $input,
                ($data['comment_text'] ?? '') !== '' ? (string) $data['comment_text'] : null,
                !empty($data['do_offline']),
                $backToStock,
                isset($data['comment_customer_notify']),
                isset($data['is_visible_on_front']),
                !empty($data['send_email']),
                $this->_initInvoice($order) ?: null,
                function (Mage_Sales_Model_Order_Creditmemo $creditmemo) use ($data): void {
                    Mage::dispatchEvent('adminhtml_sales_order_creditmemo_register_before', [
                        'creditmemo' => $creditmemo,
                        'request' => $this->getRequest(),
                    ]);
                    Mage::register('current_creditmemo', $creditmemo, true);
                    if (isset($data['do_refund'])) {
                        $creditmemo->setRefundRequested(true);
                    }
                },
            );
            $this->_getSession()->addSuccess($this->__('The credit memo has been created.'));
            Mage::getSingleton('adminhtml/session')->getCommentText(true);
            $this->_redirect('*/sales_order/view', ['order_id' => $orderId]);
            return;
        } catch (Mage_Core_Exception_NoSuchEntity|Mage_Core_Exception_Conflict $e) {
            // The new credit memo page cannot open for this order, so show the message on the order
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/sales_order/view', ['order_id' => $orderId]);
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            Mage::getSingleton('adminhtml/session')->setFormData($data);
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('Cannot save the credit memo.'));
        }
        $this->_redirect('*/*/new', ['_current' => true]);
    }

    /**
     * Cancel creditmemo action
     */
    #[Maho\Config\Route('/admin/sales_order_creditmemo/cancel')]
    public function cancelAction(): void
    {
        $creditmemo = $this->_initCreditmemo();
        if ($creditmemo) {
            try {
                Mage::getService('sales/order_document')->cancelCreditmemo($creditmemo);
                $this->_getSession()->addSuccess($this->__('The credit memo has been canceled.'));
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception) {
                $this->_getSession()->addError($this->__('Unable to cancel the credit memo.'));
            }
            $this->_redirect('*/*/view', ['creditmemo_id' => $creditmemo->getId()]);
        } else {
            $this->_forward('noRoute');
        }
    }

    /**
     * Void creditmemo action
     */
    #[Maho\Config\Route('/admin/sales_order_creditmemo/void')]
    public function voidAction(): void
    {
        $creditmemo = $this->_initCreditmemo();
        if ($creditmemo) {
            try {
                Mage::getService('sales/order_document')->voidCreditmemo($creditmemo);
                $this->_getSession()->addSuccess($this->__('The credit memo has been voided.'));
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception) {
                $this->_getSession()->addError($this->__('Unable to void the credit memo.'));
            }
            $this->_redirect('*/*/view', ['creditmemo_id' => $creditmemo->getId()]);
        } else {
            $this->_forward('noRoute');
        }
    }

    /**
     * Add comment to creditmemo history
     */
    #[Maho\Config\Route('/admin/sales_order_creditmemo/addComment')]
    public function addCommentAction(): void
    {
        try {
            $this->getRequest()->setParam('creditmemo_id', $this->getRequest()->getParam('id'));
            $data = (array) $this->getRequest()->getPost('comment');
            Mage::getService('sales/order_document')->addComment(
                $this->_initCreditmemo() ?: Mage::throwException($this->__('The credit memo no longer exists.')),
                (string) ($data['comment'] ?? ''),
                !empty($data['is_customer_notified']),
                isset($data['is_visible_on_front']),
            );

            $this->loadLayout();
            $response = $this->getLayout()->getBlock('creditmemo_comments')->toHtml();
        } catch (Mage_Core_Exception $e) {
            $response = Mage::helper('core')->jsonEncode(['error' => true, 'message' => $e->getMessage()]);
        } catch (Exception $e) {
            Mage::logException($e);
            $response = Mage::helper('core')->jsonEncode(['error' => true, 'message' => $this->__('Cannot add new comment.')]);
        }
        $this->getResponse()->setBody($response);
    }

    /**
     * Create pdf for current creditmemo
     */
    #[\Override]
    #[Maho\Config\Route('/admin/sales_order_creditmemo/print')]
    public function printAction(): void
    {
        $this->_initCreditmemo();
        parent::printAction();
    }
}
