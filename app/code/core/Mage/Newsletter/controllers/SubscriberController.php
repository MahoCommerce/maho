<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

class Mage_Newsletter_SubscriberController extends Mage_Core_Controller_Front_Action
{
    /**
     * New subscription action
     */
    #[Maho\Config\Route('/newsletter/subscriber/new', name: 'newsletter.subscriber.new', methods: ['POST'])]
    public function newAction(): void
    {
        if (!$this->_validateFormKey()) {
            $this->_redirectReferer();
            return;
        }

        if ($this->getRequest()->isPost() && $this->getRequest()->getPost('email')) {
            $session            = Mage::getSingleton('core/session');
            $customerSession    = Mage::getSingleton('customer/session');
            $email              = (string) $this->getRequest()->getPost('email');

            try {
                if (!Mage::helper('core')->isValidEmail($email)) {
                    Mage::throwException($this->__('Please enter a valid email address.'));
                }

                if (Mage::getStoreConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_ALLOW_GUEST_SUBSCRIBE_FLAG) != 1 &&
                    !$customerSession->isLoggedIn()
                ) {
                    Mage::throwException($this->__('Sorry, but administrator denied subscription for guests. Please <a href="%s">register</a>.', Mage::helper('customer')->getRegisterUrl()));
                }

                $ownerId = Mage::getModel('customer/customer')
                        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
                        ->loadByEmail($email)
                        ->getId();
                if ($ownerId !== null && $ownerId != $customerSession->getId()) {
                    Mage::throwException($this->__('This email address is already assigned to another user.'));
                }

                $status = Mage::getModel('newsletter/subscriber')->subscribe($email);
                if ($status == Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE) {
                    $session->addSuccess($this->__('Confirmation request has been sent.'));
                } else {
                    $session->addSuccess($this->__('Thank you for your subscription.'));
                }
            } catch (Mage_Core_Exception $e) {
                $session->addException($e, $this->__('There was a problem with the subscription: %s', $e->getMessage()));
            } catch (Exception $e) {
                $session->addException($e, $this->__('There was a problem with the subscription.'));
            }
        }
        $this->_redirectReferer();
    }

    /**
     * Subscription confirm action
     */
    #[Maho\Config\Route('/newsletter/subscriber/confirm', name: 'newsletter.subscriber.confirm', methods: ['GET'])]
    public function confirmAction(): void
    {
        $id    = (int) $this->getRequest()->getParam('id');
        $code  = (string) $this->getRequest()->getParam('code');

        if ($id && $code) {
            $subscriber = Mage::getModel('newsletter/subscriber')->load($id);
            $session = Mage::getSingleton('core/session');

            if ($subscriber->getStatus() == $subscriber::STATUS_SUBSCRIBED) {
                $session->addNotice($this->__('This email address is already confirmed.'));
            } elseif ($subscriber->getId() && $subscriber->getCode()) {
                if ($subscriber->confirm($code)) {
                    $subscriber->sendConfirmationSuccessEmail();
                    $session->addSuccess($this->__('Your subscription has been confirmed.'));
                } else {
                    $session->addError($this->__('Invalid subscription confirmation code.'));
                }
            } else {
                $session->addError($this->__('Invalid subscription ID.'));
            }
        }

        $this->_redirectUrl(Mage::getBaseUrl());
    }

    /**
     * Unsubscribe newsletter
     *
     * Accepts POST in addition to GET for RFC 8058 one-click unsubscribe. Mail clients that
     * honour the List-Unsubscribe-Post header send a server-to-server POST (no cookies, no
     * referer) with body "List-Unsubscribe=One-Click"; that request is answered with a bare
     * status code, not a redirect. A regular GET (the link a human clicks) keeps redirecting.
     *
     * POST status policy: 200 on success and on an invalid/expired code alike, so the endpoint
     * is not an enumeration oracle and a stale link does not surface as an error to the mail
     * client. Only an unexpected server-side failure returns 503, so the mail client retries
     * (unsubscribe is idempotent) rather than reporting a success that never happened.
     */
    #[Maho\Config\Route('/newsletter/subscriber/unsubscribe', name: 'newsletter.subscriber.unsubscribe', methods: ['GET', 'POST'])]
    public function unsubscribeAction(): void
    {
        $id    = (int) $this->getRequest()->getParam('id');
        $code  = (string) $this->getRequest()->getParam('code');
        $isPost = $this->getRequest()->isPost();

        if ($id && $code) {
            $session = Mage::getSingleton('core/session');
            try {
                Mage::getModel('newsletter/subscriber')->load($id)
                    ->setCheckCode($code)
                    ->unsubscribe();
                if (!$isPost) {
                    $session->addSuccess($this->__('You have been unsubscribed.'));
                }
            } catch (Mage_Core_Exception $e) {
                // Invalid/expired code: a client-side condition, not a server failure.
                if (!$isPost) {
                    $session->addException($e, $e->getMessage());
                }
            } catch (Exception $e) {
                Mage::logException($e);
                if ($isPost) {
                    $this->getResponse()->setHttpResponseCode(503)->setBody('');
                    return;
                }
                $session->addException($e, $this->__('There was a problem with the un-subscription.'));
            }
        }

        if ($isPost) {
            $this->getResponse()->setHttpResponseCode(200)->setBody('');
            return;
        }
        $this->_redirectReferer();
    }
}
