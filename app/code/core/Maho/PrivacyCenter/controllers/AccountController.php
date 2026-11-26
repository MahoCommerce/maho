<?php

/**
 * The Privacy Center pages of the customer account.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Request as Request;

class Maho_PrivacyCenter_AccountController extends Mage_Core_Controller_Front_Action
{
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();
        if (!$this->getRequest()->isDispatched()) {
            return $this;
        }
        // The terms and conditions of Mage_Checkout ask for an acceptance also when the store takes no privacy requests
        $isAgreementPage = in_array($this->getRequest()->getActionName(), ['agreements', 'acceptAgreements'], true);
        if (!$isAgreementPage && !Mage::helper('privacycenter')->isEnabled()) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            $this->_forward('noRoute');
            return $this;
        }
        if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }
        return $this;
    }

    #[Maho\Config\Route('/privacy/account', name: 'privacy.account', methods: ['GET'])]
    public function indexAction(): void
    {
        $this->renderPage($this->__('Privacy Center'));
    }

    #[Maho\Config\Route('/privacy/account/data', name: 'privacy.account.data', methods: ['GET'])]
    public function dataAction(): void
    {
        $this->renderPage($this->__('My Personal Data'));
    }

    #[Maho\Config\Route('/privacy/account/agreements', name: 'privacy.account.agreements', methods: ['GET'])]
    public function agreementsAction(): void
    {
        $this->renderPage($this->__('Terms and Conditions'));
    }

    #[Maho\Config\Route('/privacy/account/requests', name: 'privacy.account.requests', methods: ['GET'])]
    public function requestsAction(): void
    {
        $this->renderPage($this->__('Delete My Account'));
    }

    #[Maho\Config\Route('/privacy/account/export', name: 'privacy.account.export', methods: ['POST'])]
    public function exportAction(): void
    {
        $this->submit(Request::TYPE_EXPORT, $this->__('We sent you an email. Open the link in the email to confirm the request.'));
        $this->_redirect('*/*/data');
    }

    #[Maho\Config\Route('/privacy/account/erase', name: 'privacy.account.erase', methods: ['POST'])]
    public function eraseAction(): void
    {
        if (!$this->getRequest()->getPost('confirm_erasure')) {
            $this->getSession()->addError($this->__('Please confirm that you want to delete your account.'));
        } else {
            $this->submit(Request::TYPE_ERASURE, $this->__('We sent you an email. Open the link in the email to confirm the deletion of your account.'));
        }
        $this->_redirect('*/*/requests');
    }

    #[Maho\Config\Route('/privacy/account/cancel', name: 'privacy.account.cancel', methods: ['POST'])]
    public function cancelAction(): void
    {
        $session = $this->getSession();
        try {
            Mage::getService('privacycenter/request')->cancelForCustomer(
                (int) $this->getRequest()->getPost('request_id'),
                (int) $session->getCustomerId(),
            );
            $session->addSuccess($this->__('The request has been canceled.'));
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        }
        $this->_redirect('*/*/requests');
    }

    #[Maho\Config\Route('/privacy/account/download', name: 'privacy.account.download', methods: ['GET'])]
    public function downloadAction(): void
    {
        try {
            $file = Mage::getService('privacycenter/request')->openExportForCustomer(
                (int) $this->getRequest()->getParam('id'),
                (int) $this->getSession()->getCustomerId(),
            );
        } catch (Mage_Core_Exception $e) {
            $this->getSession()->addError($e->getMessage());
            $this->_redirect('*/*/data');
            return;
        }
        $this->_prepareDownloadResponse($file['filename'], [
            'value' => $file['stream'],
            'type' => 'stream',
        ], 'application/zip', $file['size']);
    }

    #[Maho\Config\Route('/privacy/account/acceptAgreements', name: 'privacy.account.acceptAgreements', methods: ['POST'])]
    public function acceptAgreementsAction(): void
    {
        $session = $this->getSession();
        $http = Mage::helper('core/http');
        try {
            Mage::getService('checkout/agreement')->acceptForCustomer(
                $session->getCustomer(),
                (int) Mage::app()->getStore()->getId(),
                array_map(intval(...), array_keys((array) $this->getRequest()->getPost('agreement', []))),
                Mage::getModel('checkout/agreement_acceptance')
                    ->setRemoteIp($http->getRemoteAddr() ?: null)
                    ->setUserAgent($http->getHttpUserAgent() ?: null),
            );
            $session->addSuccess($this->__('Thank you. Your acceptance has been saved.'));
        } catch (Mage_Core_Exception_Input $e) {
            $session->addError(implode(' ', array_column($e->getErrors(), 'message')));
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        }
        $this->_redirect('*/*/agreements');
    }

    protected function submit(string $type, string $successMessage): void
    {
        $session = $this->getSession();
        $http = Mage::helper('core/http');
        try {
            Mage::getService('privacycenter/request')->submitForCustomer(
                $session->getCustomer(),
                $type,
                new \Maho\DataObject([
                    'store_id' => (int) Mage::app()->getStore()->getId(),
                    'remote_ip' => $http->getRemoteAddr(),
                    'user_agent' => $http->getHttpUserAgent(),
                ]),
            );
            $session->addSuccess($successMessage);
        } catch (Mage_Core_Exception_Input $e) {
            $session->addError(implode(' ', array_column($e->getErrors(), 'message')));
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        }
    }

    protected function renderPage(string $title): void
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->getLayout()->getBlock('head')?->setTitle($title);
        $this->renderLayout();
    }

    protected function getSession(): Mage_Customer_Model_Session
    {
        return Mage::getSingleton('customer/session');
    }
}
