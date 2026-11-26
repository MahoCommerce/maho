<?php

/**
 * Customers > Privacy Requests, and the privacy actions of the customer page.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Request as Request;

class Maho_PrivacyCenter_Adminhtml_Customer_PrivacyController extends Mage_Adminhtml_Controller_Action
{
    public const string ADMIN_RESOURCE = 'customer/privacy';

    #[Maho\Config\Route('/admin/customer_privacy/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Customers'))->_title($this->__('Privacy Requests'));
        $this->initAction();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/customer_privacy/grid')]
    public function gridAction(): void
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/customer_privacy/view')]
    public function viewAction(): void
    {
        $request = $this->initRequest();
        if (!$request) {
            $this->_redirect('*/*/');
            return;
        }
        Mage::register('current_privacy_request', $request);
        $this->_title($this->__('Customers'))->_title($this->__('Privacy Requests'))
            ->_title($this->__('Request #%s', $request->getId()));
        $this->initAction();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/customer_privacy/approve')]
    public function approveAction(): void
    {
        $this->runOnRequest(fn(Request $request) => $this->getService()->approve($request, $this->getAdminUserId()), $this->__('The erasure has been approved.'));
    }

    #[Maho\Config\Route('/admin/customer_privacy/reject')]
    public function rejectAction(): void
    {
        $reason = trim((string) $this->getRequest()->getParam('reason'));
        $this->runOnRequest(fn(Request $request) => $this->getService()->reject($request, $this->getAdminUserId(), $reason), $this->__('The request has been rejected.'));
    }

    #[Maho\Config\Route('/admin/customer_privacy/process')]
    public function processAction(): void
    {
        $force = (bool) $this->getRequest()->getParam('force');
        $this->runOnRequest(fn(Request $request) => $this->getService()->process($request, $force), $this->__('The request has been processed.'));
    }

    #[Maho\Config\Route('/admin/customer_privacy/resend')]
    public function resendAction(): void
    {
        $this->runOnRequest(fn(Request $request) => $this->getService()->resendEmail($request), $this->__('The email has been sent again.'));
    }

    #[Maho\Config\Route('/admin/customer_privacy/saveNote', methods: ['POST'])]
    public function saveNoteAction(): void
    {
        $note = trim((string) $this->getRequest()->getPost('note'));
        $this->runOnRequest(
            fn(Request $request) => $note === '' ? $request : $this->getService()->save($request->appendAdminNote($note)),
            $this->__('The note has been saved.'),
        );
    }

    #[Maho\Config\Route('/admin/customer_privacy/download')]
    public function downloadAction(): void
    {
        $request = $this->initRequest();
        if (!$request) {
            $this->_redirect('*/*/');
            return;
        }
        try {
            $file = $this->getService()->openExportForStaff($request);
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/*/view', ['id' => $request->getId()]);
            return;
        }
        $this->_prepareDownloadResponse($file['filename'], ['type' => 'stream', 'value' => $file['stream']], 'application/zip', $file['size']);
    }

    /**
     * Create a request for a customer, from the Privacy tab of the customer page.
     */
    #[Maho\Config\Route('/admin/customer_privacy/create', methods: ['POST'])]
    public function createAction(): void
    {
        $customerId = (int) $this->getRequest()->getPost('customer_id');
        try {
            $customer = Mage::getService('customer/customer')->getById($customerId);
            $request = $this->getService()->submitByStaff($customer, (string) $this->getRequest()->getPost('type'), $this->getAdminUserId());
            $this->_getSession()->addSuccess($this->__('Privacy request #%s has been created.', $request->getId()));
            $this->_redirect('*/*/view', ['id' => $request->getId()]);
            return;
        } catch (Mage_Core_Exception_Input $e) {
            $this->_getSession()->addError(implode(' ', array_column($e->getErrors(), 'message')));
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        $this->_redirect('*/customer/edit', ['id' => $customerId]);
    }

    #[Maho\Config\Route('/admin/customer_privacy/massApprove')]
    public function massApproveAction(): void
    {
        $this->runOnSelection(fn(Request $request) => $this->getService()->approve($request, $this->getAdminUserId()));
    }

    #[Maho\Config\Route('/admin/customer_privacy/massReject')]
    public function massRejectAction(): void
    {
        $this->runOnSelection(fn(Request $request) => $this->getService()->reject($request, $this->getAdminUserId()));
    }

    #[Maho\Config\Route('/admin/customer_privacy/exportCsv')]
    public function exportCsvAction(): void
    {
        $content = $this->getLayout()->createBlock('privacycenter/adminhtml_request_grid')->getCsvFile();
        $this->_prepareDownloadResponse('privacy_requests.csv', $content);
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        $resource = match (strtolower($this->getRequest()->getActionName())) {
            'approve', 'reject', 'process', 'resend', 'savenote', 'download', 'create', 'massapprove', 'massreject' => 'customer/privacy/process',
            default => 'customer/privacy/view',
        };
        return Mage::getSingleton('admin/session')->isAllowed($resource);
    }

    protected function initAction(): static
    {
        $this->loadLayout()
            ->_setActiveMenu('customer/privacy')
            ->_addBreadcrumb($this->__('Customers'), $this->__('Customers'))
            ->_addBreadcrumb($this->__('Privacy Requests'), $this->__('Privacy Requests'));
        return $this;
    }

    protected function initRequest(): ?Request
    {
        try {
            return $this->getService()->getById((int) $this->getRequest()->getParam('id'));
        } catch (Mage_Core_Exception_NoSuchEntity) {
            $this->_getSession()->addError($this->__('This privacy request no longer exists.'));
            return null;
        }
    }

    /**
     * @param callable(Request): mixed $action
     */
    protected function runOnRequest(callable $action, string $successMessage): void
    {
        $request = $this->initRequest();
        if (!$request) {
            $this->_redirect('*/*/');
            return;
        }
        try {
            $action($request);
            $this->_getSession()->addSuccess($successMessage);
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        $this->_redirect('*/*/view', ['id' => $request->getId()]);
    }

    /**
     * @param callable(Request): mixed $action
     */
    protected function runOnSelection(callable $action): void
    {
        $ids = $this->getRequest()->getParam('request');
        if (!is_array($ids) || $ids === []) {
            $this->_getSession()->addError($this->__('Please select request(s).'));
            $this->_redirect('*/*/index');
            return;
        }
        $done = 0;
        foreach ($ids as $id) {
            try {
                $action($this->getService()->getById((int) $id));
                $done++;
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($this->__('Request #%s: %s', (int) $id, $e->getMessage()));
            }
        }
        $this->_getSession()->addSuccess($this->__('Total of %d request(s) were updated.', $done));
        $this->_redirect('*/*/index');
    }

    protected function getService(): Maho_PrivacyCenter_Service_Request
    {
        return Mage::getService('privacycenter/request');
    }

    protected function getAdminUserId(): ?int
    {
        $user = Mage::getSingleton('admin/session')->getUser();
        return $user?->getId() ? (int) $user->getId() : null;
    }
}
