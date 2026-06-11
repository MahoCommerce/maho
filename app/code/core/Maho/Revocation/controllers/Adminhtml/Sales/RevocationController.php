<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

class Maho_Revocation_Adminhtml_Sales_RevocationController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'sales/revocation';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['save', 'process', 'linkOrder', 'resend', 'massAccept', 'massReject']);
        return parent::preDispatch();
    }

    protected function _initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('sales/revocation')
            ->_addBreadcrumb($this->__('Sales'), $this->__('Sales'))
            ->_addBreadcrumb($this->__('Revocation Requests'), $this->__('Revocation Requests'));
        return $this;
    }

    protected function _initRequest(): ?Maho_Revocation_Model_Request
    {
        $id = (int) $this->getRequest()->getParam('id');
        $model = Mage::getModel('revocation/request')->load($id);
        if (!$model->getId()) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('This revocation request no longer exists.'));
            return null;
        }
        return $model;
    }

    #[Maho\Config\Route('/admin/sales_revocation/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Sales'))->_title($this->__('Revocation Requests'));
        $this->_initAction();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/sales_revocation/grid')]
    public function gridAction(): void
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/sales_revocation/view')]
    public function viewAction(): void
    {
        $model = $this->_initRequest();
        if (!$model) {
            $this->_redirect('*/*/');
            return;
        }

        Mage::register('current_revocation_request', $model);

        $this->_title($this->__('Sales'))->_title($this->__('Revocation Requests'))
            ->_title($this->__('Request #%s', $model->getId()));
        $this->_initAction()
            ->_addBreadcrumb($this->__('View Request'), $this->__('View Request'));
        $this->renderLayout();
    }

    /**
     * Save the internal note (and optionally the processed status) without touching the order.
     */
    #[Maho\Config\Route('/admin/sales_revocation/save')]
    public function saveAction(): void
    {
        $model = $this->_initRequest();
        if (!$model) {
            $this->_redirect('*/*/');
            return;
        }

        try {
            $note = trim((string) $this->getRequest()->getParam('admin_note'));
            $model->setAdminNote($note !== '' ? $note : null);

            $service = Mage::getModel('revocation/service');
            $processedStatus = (string) $this->getRequest()->getParam('processed_status');
            if ($processedStatus !== '' && $service->isValidProcessedStatus($processedStatus)) {
                $service->applyProcessedStatus($model, $processedStatus);
            }

            $model->save();
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The revocation request has been saved.'));
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/view', ['id' => $model->getId()]);
    }

    /**
     * Accept or reject the revocation. Applies the corresponding order status to the
     * matched order (status only, never the order state). Accepting redirects to the
     * creditmemo creation page so the merchant can process the refund via the
     * existing flow.
     */
    #[Maho\Config\Route('/admin/sales_revocation/process')]
    public function processAction(): void
    {
        $model = $this->_initRequest();
        if (!$model) {
            $this->_redirect('*/*/');
            return;
        }

        $decision = (string) $this->getRequest()->getParam('decision');
        if (!in_array($decision, ['accept', 'reject'], true)) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Invalid decision.'));
            $this->_redirect('*/*/view', ['id' => $model->getId()]);
            return;
        }

        $accepted = $decision === 'accept';
        $order = $model->getOrder();

        try {
            $model->setProcessedStatus($accepted
                ? Maho_Revocation_Model_Request::PROCESSED_STATUS_ACCEPTED
                : Maho_Revocation_Model_Request::PROCESSED_STATUS_REJECTED);
            $model->setProcessedAt(Mage::app()->getLocale()->formatDateForDb('now'));
            $model->save();

            // Audit note only: the revocation outcome lives on the request, never on the
            // order's status/state. A revocation can target an order in any state, and the
            // refund (when accepted) is handled through the normal credit memo flow, which
            // the detail view links to.
            if ($order) {
                $history = $order->addStatusHistoryComment(
                    $accepted
                        ? $this->__('Revocation request #%s accepted.', $model->getId())
                        : $this->__('Revocation request #%s rejected.', $model->getId()),
                );
                $history->setIsCustomerNotified(false);
                $order->save();
            }

            Mage::getSingleton('adminhtml/session')->addSuccess($accepted
                ? $this->__('The revocation has been marked as accepted.')
                : $this->__('The revocation has been marked as rejected.'));
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/view', ['id' => $model->getId()]);
    }

    /**
     * Manually link an unmatched request to an order after triage.
     */
    #[Maho\Config\Route('/admin/sales_revocation/linkOrder')]
    public function linkOrderAction(): void
    {
        $model = $this->_initRequest();
        if (!$model) {
            $this->_redirect('*/*/');
            return;
        }

        $incrementId = trim((string) $this->getRequest()->getParam('order_increment_id'));
        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        if (!$order->getId()) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('No order found for increment ID "%s".', $incrementId));
            $this->_redirect('*/*/view', ['id' => $model->getId()]);
            return;
        }

        try {
            $model->setOrderId((int) $order->getId())
                ->appendAdminNote(sprintf('Manually linked to order %s', $order->getIncrementId()))
                ->save();
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The request has been linked to order %s.', $order->getIncrementId()));
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/view', ['id' => $model->getId()]);
    }

    /**
     * Resend the customer receipt email; also clears a rate-limit suppression.
     */
    #[Maho\Config\Route('/admin/sales_revocation/resend')]
    public function resendAction(): void
    {
        $model = $this->_initRequest();
        if (!$model) {
            $this->_redirect('*/*/');
            return;
        }

        try {
            if (Mage::getModel('revocation/service')->resendReceipt($model)) {
                Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The receipt email has been resent to %s.', $model->getEmail()));
            } else {
                Mage::getSingleton('adminhtml/session')->addError($this->__('The receipt email could not be sent. Please check the email configuration.'));
            }
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/view', ['id' => $model->getId()]);
    }

    #[Maho\Config\Route('/admin/sales_revocation/massAccept')]
    public function massAcceptAction(): void
    {
        $this->_massProcess(Maho_Revocation_Model_Request::PROCESSED_STATUS_ACCEPTED);
    }

    #[Maho\Config\Route('/admin/sales_revocation/massReject')]
    public function massRejectAction(): void
    {
        $this->_massProcess(Maho_Revocation_Model_Request::PROCESSED_STATUS_REJECTED);
    }

    #[Maho\Config\Route('/admin/sales_revocation/exportCsv')]
    public function exportCsvAction(): void
    {
        $content = $this->getLayout()->createBlock('revocation/adminhtml_request_grid')->getCsvFile();
        $this->_prepareDownloadResponse('revocation_requests.csv', $content);
    }

    /**
     * Mass actions only mark the request rows; order statuses are applied one by one
     * from the detail view where the merchant sees the matched order.
     */
    protected function _massProcess(string $processedStatus): void
    {
        $requestIds = $this->getRequest()->getParam('request');
        if (!is_array($requestIds) || $requestIds === []) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Please select request(s).'));
            $this->_redirect('*/*/index');
            return;
        }

        $processed = 0;
        try {
            foreach ($requestIds as $requestId) {
                $model = Mage::getModel('revocation/request')->load((int) $requestId);
                if (!$model->getId()) {
                    continue;
                }
                $model->setProcessedStatus($processedStatus);
                $model->setProcessedAt(Mage::app()->getLocale()->formatDateForDb('now'));
                $model->save();
                $processed++;
            }
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Total of %d request(s) were updated.', $processed));
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/index');
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        $action = strtolower($this->getRequest()->getActionName());
        $resource = match ($action) {
            'save', 'process', 'linkorder', 'resend', 'massaccept', 'massreject' => 'sales/revocation/process',
            default => 'sales/revocation/view',
        };
        return Mage::getSingleton('admin/session')->isAllowed($resource);
    }
}
