<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

class Mage_Newsletter_ManageController extends Mage_Core_Controller_Front_Action
{
    /**
     * Action predispatch
     *
     * Check customer authentication for some actions
     */
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();
        if (!Mage::getStoreConfigFlag('customer/account/enabled_in_frontend')) {
            $this->norouteAction();
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return $this;
        }
        if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $this->setFlag('', 'no-dispatch', true);
        }
        return $this;
    }

    #[Maho\Config\Route('/newsletter/manage', name: 'newsletter.manage.index', methods: ['GET'])]
    public function indexAction(): void
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');

        if ($block = $this->getLayout()->getBlock('customer_newsletter')) {
            $block->setRefererUrl($this->_getRefererUrl());
        }
        $this->getLayout()->getBlock('head')->setTitle($this->__('Newsletter Subscription'));
        $this->renderLayout();
    }

    #[Maho\Config\Route('/newsletter/manage/save', name: 'newsletter.manage.save', methods: ['POST'])]
    public function saveAction()
    {
        $session = Mage::getSingleton('customer/session');
        $customerId = (int) $session->getCustomerId();
        $service = Mage::getService('newsletter/subscriber');
        try {
            if ($this->getRequest()->getParam('is_subscribed')) {
                $service->subscribeCustomer($customerId, (int) Mage::app()->getStore()->getId(), Mage_Newsletter_Model_Channel::Web);
                $session->addSuccess($this->__('The subscription has been saved.'));
            } else {
                $service->unsubscribeCustomer($customerId);
                $session->addSuccess($this->__('The subscription has been removed.'));
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $session->addError($this->__('An error occurred while saving your subscription.'));
        }
        $this->_redirect('customer/account/');
    }
}
