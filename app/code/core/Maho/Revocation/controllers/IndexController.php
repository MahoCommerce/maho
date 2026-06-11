<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

/**
 * Public revocation form (EU Directive 2023/2673, §356a BGB).
 *
 * Must stay accessible without login. Abuse gates are deliberately invisible to
 * legitimate users: honeypot and timing failures return the same success page a
 * real submit gets, so bots receive no signal that a protection exists.
 */
class Maho_Revocation_IndexController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/revocation', name: 'revocation.form', methods: ['GET'])]
    public function indexAction(): void
    {
        if (!Mage::helper('revocation')->isEnabled()) {
            $this->_forward('noRoute');
            return;
        }

        $this->_registerPrefillOrder();

        $this->loadLayout();
        $this->_initLayoutMessages('core/session');
        if ($head = $this->getLayout()->getBlock('head')) {
            $head->setTitle(Mage::helper('revocation')->__('Revoke contract'));
        }
        $this->renderLayout();
    }

    #[Maho\Config\Route('/revocation/submit', name: 'revocation.submit', methods: ['POST'])]
    public function submitAction(): void
    {
        // Captured before any gate so the legal receipt reflects the actual submit moment.
        $receivedAt = microtime(true);

        if (!Mage::helper('revocation')->isEnabled()) {
            $this->_forward('noRoute');
            return;
        }

        $session = Mage::getSingleton('core/session');
        $request = $this->getRequest();

        if (!$request->isPost()) {
            $this->_redirectUrl(Mage::getUrl('revocation/index/index'));
            return;
        }

        if (!$this->_validateFormKey()) {
            $session->addError($this->__('Invalid form key. Please refresh the page and try again.'));
            $this->_redirectUrl(Mage::getUrl('revocation/index/index'));
            return;
        }

        // Honeypot: hidden field humans never see. Bots that fill it get the normal
        // success page so they cannot detect the trap. No row, no email.
        if (trim((string) $request->getParam('comment_body')) !== '') {
            $this->_fakeSuccess($receivedAt);
            return;
        }

        if ($this->_isSubmittedTooFast($receivedAt)) {
            $this->_fakeSuccess($receivedAt);
            return;
        }

        $ip = (string) Mage::helper('core/http')->getRemoteAddr();
        if (Mage::helper('revocation')->isIpRateLimited($ip)) {
            $session->addError($this->__('Your request could not be processed right now. Please try again later or contact us directly.'));
            $this->_redirectUrl(Mage::getUrl('revocation/index/index'));
            return;
        }

        $input = new \Maho\DataObject([
            'customer_name' => $request->getParam('customer_name'),
            'email' => $request->getParam('email'),
            'order_reference' => $request->getParam('order_reference'),
            'reason' => $request->getParam('reason'),
            'ip' => $ip,
            'user_agent' => $request->getHeader('User-Agent'),
            'locale' => Mage::app()->getLocale()->getLocaleCode(),
            'store_id' => (int) Mage::app()->getStore()->getId(),
            'received_at_microtime' => $receivedAt,
        ]);

        $customerSession = Mage::getSingleton('customer/session');
        if ($customerSession->isLoggedIn() && ($sessionOrderId = (int) $request->getParam('session_order_id'))) {
            $input->setCustomerId((int) $customerSession->getCustomerId());
            $input->setSessionOrderId($sessionOrderId);
        }

        try {
            $revocationRequest = Mage::getModel('revocation/service')->submit($input);
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
            $session->setRevocationFormData($request->getPost());
            $this->_redirectUrl(Mage::getUrl('revocation/index/index'));
            return;
        }

        $session->setRevocationSuccess([
            'request_id' => (int) $revocationRequest->getId(),
            'received_at' => $revocationRequest->getReceivedAt(),
        ]);
        $this->_redirectUrl(Mage::getUrl('revocation/index/success'));
    }

    #[Maho\Config\Route('/revocation/success', name: 'revocation.success', methods: ['GET'])]
    public function successAction(): void
    {
        if (!Mage::helper('revocation')->isEnabled()) {
            $this->_forward('noRoute');
            return;
        }

        $data = Mage::getSingleton('core/session')->getRevocationSuccess(true);
        if (!is_array($data)) {
            $this->_redirectUrl(Mage::getUrl('revocation/index/index'));
            return;
        }

        Mage::register('revocation_success', $data);

        $this->loadLayout();
        if ($head = $this->getLayout()->getBlock('head')) {
            $head->setTitle(Mage::helper('revocation')->__('Revocation received'));
        }
        $this->renderLayout();
    }

    /**
     * Pre-fill support for the my-account entry point: only honored for orders owned
     * by the logged-in session, so the public path can never use it.
     */
    protected function _registerPrefillOrder(): void
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        $customerSession = Mage::getSingleton('customer/session');
        if (!$orderId || !$customerSession->isLoggedIn()) {
            return;
        }

        $order = Mage::getModel('sales/order')->load($orderId);
        if ($order->getId() && (int) $order->getCustomerId() === (int) $customerSession->getCustomerId()) {
            Mage::register('revocation_prefill_order', $order);
        }
    }

    /**
     * Submit-time check: the form embeds an encrypted render timestamp; anything
     * faster than the configured minimum is bot traffic. Missing or unreadable
     * tokens count as too fast.
     */
    protected function _isSubmittedTooFast(float $receivedAt): bool
    {
        $minSeconds = (int) Mage::getStoreConfig(Maho_Revocation_Helper_Data::XML_PATH_MIN_SUBMIT_SECONDS);
        if ($minSeconds <= 0) {
            return false;
        }

        $renderedAt = (string) Mage::helper('core')->decrypt((string) $this->getRequest()->getParam('frt'));
        if (!ctype_digit($renderedAt)) {
            return true;
        }

        return ($receivedAt - (float) $renderedAt) < $minSeconds;
    }

    /**
     * Indistinguishable-from-success response for silently dropped submissions.
     */
    protected function _fakeSuccess(float $receivedAt): void
    {
        Mage::getSingleton('core/session')->setRevocationSuccess([
            'request_id' => random_int(1000, 999999),
            'received_at' => Mage::app()->getLocale()->formatDateForDb((int) $receivedAt),
        ]);
        $this->_redirectUrl(Mage::getUrl('revocation/index/success'));
    }
}
