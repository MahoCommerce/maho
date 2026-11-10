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
        try {
            return $this->_getService()->getById((int) $this->getRequest()->getParam('id'));
        } catch (Mage_Core_Exception_NoSuchEntity) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('This revocation request no longer exists.'));
            return null;
        }
    }

    protected function _getService(): Maho_Revocation_Service_Request
    {
        return Mage::getService('revocation/request');
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
     * Save the internal note. The status and the order do not change.
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
            $this->_getService()->save($model->setAdminNote((string) $this->getRequest()->getParam('admin_note')));
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The revocation request has been saved.'));
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/view', ['id' => $model->getId()]);
    }

    /**
     * Accept or reject the revocation. The order gets a note, and its status and state do not change.
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

        try {
            $this->_getService()->decide($model, $accepted
                ? Maho_Revocation_Model_Request::PROCESSED_STATUS_ACCEPTED
                : Maho_Revocation_Model_Request::PROCESSED_STATUS_REJECTED);

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
            $this->_getService()->save($model->setOrderId((int) $order->getId())
                ->appendAdminNote(sprintf('Manually linked to order %s', $order->getIncrementId())));
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
            if ($this->_getService()->resendReceipt($model)) {
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
                if ($model->getId()) {
                    $this->_getService()->decide($model, $processedStatus);
                    $processed++;
                }
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
