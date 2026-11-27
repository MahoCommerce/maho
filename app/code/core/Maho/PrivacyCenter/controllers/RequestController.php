<?php

/**
 * The public privacy request form of the storefront, and the links of its emails.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

class Maho_PrivacyCenter_RequestController extends Mage_Core_Controller_Front_Action
{
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();
        if ($this->getRequest()->isDispatched() && !Mage::helper('privacycenter')->isEnabled()) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            $this->_forward('noRoute');
        }
        return $this;
    }

    #[Maho\Config\Route('/privacy/request', name: 'privacy.request', methods: ['GET'])]
    public function indexAction(): void
    {
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $this->_redirect('privacy/account');
            return;
        }
        $this->renderPage($this->__('Privacy Request'));
    }

    #[Maho\Config\Route('/privacy/request/submit', name: 'privacy.request.submit', methods: ['POST'])]
    public function submitAction(): void
    {
        $session = Mage::getSingleton('core/session');
        $request = $this->getRequest();
        $http = Mage::helper('core/http');
        try {
            // A bot that fills the honeypot gets the page of a real request: the service records nothing
            Mage::getService('privacycenter/request')->submit(new \Maho\DataObject([
                'email' => $request->getPost('email'),
                'type' => $request->getPost('type'),
                'store_id' => (int) Mage::app()->getStore()->getId(),
                'remote_ip' => $http->getRemoteAddr(),
                'user_agent' => $http->getHttpUserAgent(),
                'form_fields' => $request->getPost(),
            ]));
        } catch (Mage_Core_Exception_Input $e) {
            $session->addError(implode(' ', array_column($e->getErrors(), 'message')));
            $session->setPrivacyRequestFormData($request->getPost());
            $this->_redirect('privacy/request');
            return;
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
            $session->setPrivacyRequestFormData($request->getPost());
            $this->_redirect('privacy/request');
            return;
        }
        $session->addSuccess($this->__('Thank you. If the email address is correct, you will receive an email with a link to confirm your request.'));
        $this->_redirect('privacy/request/result');
    }

    #[Maho\Config\Route('/privacy/request/result', name: 'privacy.request.result', methods: ['GET'])]
    public function resultAction(): void
    {
        $this->renderPage($this->__('Privacy Request'));
    }

    #[Maho\Config\Route('/privacy/request/confirmation', name: 'privacy.request.confirmation', methods: ['GET'])]
    public function confirmationAction(): void
    {
        $token = (string) $this->getRequest()->getParam('token');
        try {
            Mage::register('privacy_request', Mage::getService('privacycenter/request')->getByConfirmationToken($token));
            Mage::register('privacy_request_token', $token);
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
            $this->_redirect('privacy/request/result');
            return;
        }
        $this->renderPage($this->__('Confirm Your Privacy Request'));
    }

    #[Maho\Config\Route('/privacy/request/confirm', name: 'privacy.request.confirm', methods: ['POST'])]
    public function confirmAction(): void
    {
        $session = Mage::getSingleton('core/session');
        try {
            $request = Mage::getService('privacycenter/request')->confirm((string) $this->getRequest()->getPost('token'));
            $session->addSuccess($request->isExport()
                ? $this->__('Thank you. We are preparing your data. We will send you an email with the download link.')
                : $this->__('Thank you. Your request is confirmed. We will send you an email when the erasure is complete.'));
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        }
        $this->_redirect('privacy/request/result');
    }

    #[Maho\Config\Route('/privacy/request/download', name: 'privacy.request.download', methods: ['GET'])]
    public function downloadAction(): void
    {
        try {
            $file = Mage::getService('privacycenter/request')->openExportByToken((string) $this->getRequest()->getParam('token'));
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
            $this->_redirect('privacy/request/result');
            return;
        }
        $this->_prepareDownloadResponse($file['filename'], [
            'value' => $file['stream'],
            'type' => 'stream',
        ], 'application/zip', $file['size']);
    }

    protected function renderPage(string $title): void
    {
        $this->loadLayout();
        $this->_initLayoutMessages('core/session');
        $this->getLayout()->getBlock('head')?->setTitle($title);
        $this->renderLayout();
    }
}
